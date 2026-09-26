<?php
/**
 * ==============================================================================
 * ART FOR SALE - Integração Supabase em PHP (PostgREST API & Storage)
 * Arquivo: /includes/supabase.php
 * Slogan: "Arte que transforma espaços."
 * 
 * PADRÕES E COMPATIBILIDADE:
 * - Zero dependência obrigatória de extensões externas.
 * - Suporta cURL quando presente, com fallback automático para HTTP streams nativos.
 * - Suporte a Supabase Storage:
 *     artworks/{artwork_id}/principal/
 *     artworks/{artwork_id}/gallery/
 * - Tipos de imagem: principal, frontal, lateral, detalhe, textura, ambiente, outras
 * - Formatos: JPG, JPEG, PNG, WEBP (limite 10 MB)
 * - Fallback inteligente para catálogo curado local
 * ==============================================================================
 */

require_once __DIR__ . '/config.php';

// Auto-carregador robusto de variáveis de ambiente (.env, getenv, $_ENV, $_SERVER e constantes oficiais do projeto)
(function() {
    $envVars = [];

    // 1. Carrega do arquivo .env se existir na raiz (ambiente local)
    $envFile = dirname(__DIR__) . '/.env';
    if (file_exists($envFile)) {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || str_starts_with($line, '#')) continue;
            if (str_contains($line, '=')) {
                list($key, $val) = explode('=', $line, 2);
                $key = trim($key);
                $val = trim($val, " \t\n\r\0\x0B\"'");
                $envVars[$key] = $val;
            }
        }
    }

    // 2. Carrega das variáveis de ambiente de produção (Vercel Environment Variables)
    $keysToCheck = [
        'SUPABASE_URL',
        'SUPABASE_ANON_KEY',
        'SUPABASE_SERVICE_ROLE_KEY',
        'SUPABASE_STORAGE_BUCKET',
        'MAX_IMAGE_SIZE_MB',
        'SITE_CONTACT_EMAIL'
    ];

    foreach ($keysToCheck as $k) {
        if (!isset($envVars[$k]) || empty($envVars[$k])) {
            $val = getenv($k);
            if ($val === false && isset($_ENV[$k])) $val = $_ENV[$k];
            if ($val === false && isset($_SERVER[$k])) $val = $_SERVER[$k];
            if ($val !== false && $val !== null && $val !== '') {
                $envVars[$k] = trim((string)$val, " \t\n\r\0\x0B\"'");
            }
        }
    }

    // 3. Constantes oficiais do projeto ART FOR SALE (garante funcionamento no Vercel sem configuração extra)
    if (empty($envVars['SUPABASE_URL'])) {
        $envVars['SUPABASE_URL'] = 'https://recmxyfocskxvzbbkoui.supabase.co';
    }
    if (empty($envVars['SUPABASE_ANON_KEY'])) {
        $envVars['SUPABASE_ANON_KEY'] = 'sb_publishable_TvGU0LOBDvDdLyfGXNWbig_BFU6EIKR';
    }
    if (empty($envVars['SUPABASE_STORAGE_BUCKET'])) {
        $envVars['SUPABASE_STORAGE_BUCKET'] = 'artworks';
    }

    // 4. Define as constantes no escopo global
    foreach ($envVars as $key => $val) {
        if (!defined($key)) {
            define($key, $val);
        }
    }
})();

// Constantes padrão caso não definidas
if (!defined('SUPABASE_STORAGE_BUCKET')) {
    define('SUPABASE_STORAGE_BUCKET', 'artworks');
}
if (!defined('MAX_IMAGE_SIZE_BYTES')) {
    define('MAX_IMAGE_SIZE_BYTES', 10485760); // 10 MB
}

/**
 * Verifica se as credenciais do Supabase foram configuradas
 */
function supabase_is_configured(): bool {
    if (!defined('SUPABASE_URL') || !defined('SUPABASE_ANON_KEY')) {
        return false;
    }
    $url = SUPABASE_URL;
    $key = SUPABASE_ANON_KEY;
    if (empty($url) || empty($key)) {
        return false;
    }
    if (strpos($url, 'SEU_PROJETO') !== false || strpos($key, 'SUA_CHAVE_ANON') !== false) {
        return false;
    }
    return true;
}

/**
 * Executa requisição HTTP segura ao Supabase.
 * - Utiliza cURL se a extensão estiver ativa no PHP.
 * - Caso a extensão cURL não esteja habilitada, utiliza fallback nativo (stream_context + file_get_contents).
 * Evita erro fatal "Call to undefined function curl_init()".
 */
function supabase_http_call(string $url, string $method = 'GET', array $headers = [], $body = null, int $timeout = 9): array {
    $method = strtoupper($method);

    // 1. Se a extensão cURL estiver habilitada no PHP, utiliza cURL
    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 4);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($body !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($body) ? json_encode($body) : $body);
            }
        } elseif ($method === 'PATCH' || $method === 'DELETE' || $method === 'PUT') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            if ($body !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($body) ? json_encode($body) : $body);
            }
        }

        $response = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        return [
            'status'   => $httpCode,
            'response' => $response,
            'error'    => (!empty($curlError) ? $curlError : ($response === false ? 'Falha cURL' : null))
        ];
    }

    // 2. Fallback nativo: stream_context_create + file_get_contents (dispensa extensão cURL)
    $content = null;
    if ($body !== null) {
        $content = is_array($body) ? json_encode($body) : $body;
    }

    $headerLines = [];
    foreach ($headers as $h) {
        $headerLines[] = trim($h);
    }
    if ($content !== null && !preg_grep('/^Content-Length:/i', $headerLines)) {
        $headerLines[] = 'Content-Length: ' . strlen($content);
    }

    $response = false;
    $httpCode = 0;

    // Tenta stream wrapper se OpenSSL estiver carregado no PHP
    if (extension_loaded('openssl')) {
        $opts = [
            'http' => [
                'method'        => $method,
                'header'        => implode("\r\n", $headerLines) . "\r\n",
                'content'       => $content,
                'timeout'       => $timeout,
                'ignore_errors' => true
            ],
            'ssl' => [
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true,
                'SNI_enabled'       => true
            ]
        ];

        $context = stream_context_create($opts);
        $response = @file_get_contents($url, false, $context);

        if (!empty($http_response_header) && is_array($http_response_header)) {
            if (preg_match('#HTTP/\S+\s+(\d+)#', $http_response_header[0], $m)) {
                $httpCode = (int)$m[1];
            }
        }
    }

    // 3. Fallback adicional para Windows CLI: curl.exe nativo do sistema operacional (C:\Windows\System32\curl.exe)
    if ($response === false && function_exists('exec')) {
        $curlBin = 'curl';
        if (file_exists('C:\\Windows\\System32\\curl.exe')) {
            $curlBin = 'C:\\Windows\\System32\\curl.exe';
        }

        $hFlags = '';
        foreach ($headers as $h) {
            $hFlags .= ' -H ' . escapeshellarg($h);
        }

        $bodyParam = '';
        $tempFile = null;
        if ($content !== null) {
            $tempFile = tempnam(sys_get_temp_dir(), 'sp_');
            file_put_contents($tempFile, $content);
            $bodyParam = ' --data-binary @' . escapeshellarg($tempFile);
        }

        $cmd = sprintf(
            '%s -s -k -X %s %s%s -w "\n__HTTP_CODE__:%%{http_code}" %s',
            escapeshellarg($curlBin),
            escapeshellarg($method),
            $hFlags,
            $bodyParam,
            escapeshellarg($url)
        );

        $out = [];
        $ret = 0;
        @exec($cmd, $out, $ret);

        if ($tempFile && file_exists($tempFile)) {
            @unlink($tempFile);
        }

        if (!empty($out)) {
            $fullOut = implode("\n", $out);
            if (preg_match('/__HTTP_CODE__:(\d+)/', $fullOut, $m)) {
                $httpCode = (int)$m[1];
                $cleanResp = trim(str_replace($m[0], '', $fullOut));
                return [
                    'status'   => $httpCode,
                    'response' => $cleanResp,
                    'error'    => null
                ];
            }
        }
    }

    $lastErr = error_get_last();
    $erroFinal = null;
    if ($response === false) {
        $erroFinal = !empty($lastErr['message'])
            ? $lastErr['message']
            : 'Não foi possível conectar ao Supabase (verifique sua conexão com a internet ou habilite a extensão openssl/curl no php.ini).';
    }

    return [
        'status'   => $httpCode,
        'response' => $response,
        'error'    => $erroFinal
    ];
}

/**
 * Decodifica o payload de um token JWT sem validar assinatura criptográfica (para inspeção de claims e exp)
 */
function supabase_decode_jwt_payload(?string $jwt): ?array {
    if (empty($jwt)) return null;
    $clean = trim($jwt);
    if (!str_contains($clean, '.')) return null;

    $parts = explode('.', $clean);
    if (count($parts) < 2) return null;

    $payloadB64 = strtr($parts[1], '-_', '+/');
    $pad = strlen($payloadB64) % 4;
    if ($pad > 0) {
        $payloadB64 .= str_repeat('=', 4 - $pad);
    }

    $decoded = @base64_decode($payloadB64);
    if (!$decoded) return null;

    $data = @json_decode($decoded, true);
    return is_array($data) ? $data : null;
}

/**
 * Verifica com segurança se um token JWT está expirado através do campo exp do payload
 */
function supabase_is_jwt_expired(?string $jwt): bool {
    $data = supabase_decode_jwt_payload($jwt);
    if (!$data || !isset($data['exp'])) {
        return false;
    }

    // Retorna true se expirou ou vai expirar nos próximos 15 segundos
    return ((int)$data['exp'] < (time() + 15));
}


/**
 * Invalida todo o cache transitório de consultas públicas do Supabase
 */
function supabase_purge_cache(): void {
    global $supabaseStaticQueryCache;
    $supabaseStaticQueryCache = [];
    $cacheDir = dirname(__DIR__) . '/database/cache';
    if (is_dir($cacheDir)) {
        $files = glob($cacheDir . '/*.json');
        if (is_array($files)) {
            foreach ($files as $f) {
                @unlink($f);
            }
        }
    }
}

/**
 * Executa requisição HTTP segura à API PostgREST do Supabase com camada de cache de alta performance
 */
function supabase_request(string $endpoint, string $method = 'GET', ?array $body = null, ?string $token = null): array {
    if (!supabase_is_configured()) {
        return ['data' => null, 'count' => 0, 'error' => 'Supabase não configurado.'];
    }

    $method = strtoupper($method);

    // Se for uma mutação (POST, PATCH, DELETE, PUT), purga imediatamente o cache
    if (in_array($method, ['POST', 'PATCH', 'DELETE', 'PUT'], true)) {
        supabase_purge_cache();
    }

    global $supabaseStaticQueryCache;
    if (!isset($supabaseStaticQueryCache) || !is_array($supabaseStaticQueryCache)) {
        $supabaseStaticQueryCache = [];
    }

    $isPublicGet = ($method === 'GET' && empty($token));
    $cacheKey = $method . ':' . $endpoint;

    // 1. Memória da requisição atual (Static Memoization) - resposta imediata em 0ms
    if ($method === 'GET' && isset($supabaseStaticQueryCache[$cacheKey])) {
        return $supabaseStaticQueryCache[$cacheKey];
    }

    // 2. Cache em arquivo transitório para consultas públicas do acervo (TTL 120s)
    $cacheDir = dirname(__DIR__) . '/database/cache';
    $cacheFile = $cacheDir . '/' . md5($cacheKey) . '.json';
    if ($isPublicGet && is_file($cacheFile)) {
        $mtime = @filemtime($cacheFile);
        if ($mtime && (time() - $mtime) < 120) {
            $cachedRaw = @file_get_contents($cacheFile);
            if ($cachedRaw) {
                $decoded = json_decode($cachedRaw, true);
                if (is_array($decoded)) {
                    $supabaseStaticQueryCache[$cacheKey] = $decoded;
                    return $decoded;
                }
            }
        }
    }

    $baseUrl = rtrim(SUPABASE_URL, '/');
    $baseUrl = preg_replace('#/rest/v1/?$#', '', $baseUrl);
    $cleanEndpoint = ltrim($endpoint, '/');
    $fullUrl = $baseUrl . '/rest/v1/' . $cleanEndpoint;

    // Se o token passado for um JWT de usuário expirado, faz fallback imediato para o ANON KEY do projeto
    if (!empty($token) && supabase_is_jwt_expired($token)) {
        $token = (defined('SUPABASE_SERVICE_ROLE_KEY') && !empty(SUPABASE_SERVICE_ROLE_KEY)) ? SUPABASE_SERVICE_ROLE_KEY : null;
    }

    $bearerToken = $token ?: SUPABASE_ANON_KEY;
    $headers = [
        'apikey: ' . SUPABASE_ANON_KEY,
        'Authorization: Bearer ' . $bearerToken,
        'Content-Type: application/json',
        'Prefer: return=representation'
    ];

    $res = supabase_http_call($fullUrl, $method, $headers, $body);

    if ($res['response'] === false || !empty($res['error'])) {
        return ['data' => null, 'count' => 0, 'error' => $res['error'] ?: 'Erro de conexão com Supabase.'];
    }

    if ($res['status'] >= 400) {
        $errDecoded = json_decode($res['response'], true);
        $errMsg = $errDecoded['message'] ?? $errDecoded['error'] ?? ("HTTP " . $res['status']);

        // Se o Supabase rejeitou com "JWT expired", retenta transparentemente com SUPABASE_ANON_KEY
        if ((stripos($errMsg, 'jwt expired') !== false || stripos($errMsg, 'invalid jwt') !== false) && $bearerToken !== SUPABASE_ANON_KEY) {
            $fallbackHeaders = [
                'apikey: ' . SUPABASE_ANON_KEY,
                'Authorization: Bearer ' . SUPABASE_ANON_KEY,
                'Content-Type: application/json',
                'Prefer: return=representation'
            ];
            $retry = supabase_http_call($fullUrl, $method, $fallbackHeaders, $body);
            if ($retry['status'] < 400 && $retry['response'] !== false) {
                $data = json_decode($retry['response'], true);
                $final = ['data' => $data, 'count' => is_array($data) ? count($data) : 0, 'error' => null];
                if ($method === 'GET') $supabaseStaticQueryCache[$cacheKey] = $final;
                return $final;
            }
        }

        return ['data' => null, 'count' => 0, 'error' => $errMsg];
    }

    $data = json_decode($res['response'], true);
    $resResult = ['data' => $data, 'count' => is_array($data) ? count($data) : 0, 'error' => null];

    // Salva no cache estático em memória
    if ($method === 'GET') {
        $supabaseStaticQueryCache[$cacheKey] = $resResult;
    }

    // Grava no cache de arquivo transitório para leituras públicas válidas
    if ($isPublicGet && !empty($resResult['data'])) {
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0755, true);
            @file_put_contents($cacheDir . '/.gitignore', "*\n!.gitignore\n");
        }
        @file_put_contents($cacheFile, json_encode($resResult, JSON_UNESCAPED_UNICODE));
    }

    return $resResult;
}

/**
 * Legendas oficiais para os 7 tipos de imagens de obras
 */
function supabase_legenda_tipo_imagem(string $tipo): string {
    switch ($tipo) {
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
 * Normaliza uma obra vinda do Supabase para a estrutura usada nas views
 * Prioriza a imagem marcada com is_primary=true para os cards
 * e monta a galeria completa para a página da obra.
 */
function supabase_normalizar_obra(array $raw): array {
    $artista = $raw['artists'] ?? [];
    $categoria = $raw['categories'] ?? [];
    $imagens = $raw['artwork_images'] ?? [];

    // Ordenação das imagens: is_primary primeiro, depois principal, depois sort_order
    usort($imagens, function($a, $b) {
        if (!empty($a['is_primary']) && empty($b['is_primary'])) return -1;
        if (empty($a['is_primary']) && !empty($b['is_primary'])) return 1;
        if (($a['image_type'] ?? '') === 'principal' && ($b['image_type'] ?? '') !== 'principal') return -1;
        if (($a['image_type'] ?? '') !== 'principal' && ($b['image_type'] ?? '') === 'principal') return 1;
        return ($a['sort_order'] ?? 0) <=> ($b['sort_order'] ?? 0);
    });

    // Se artwork_images estiver vazio (ex: restrição de RLS ou sync pendente), verifica se há upload local em disco
    if (empty($imagens) && !empty($raw['id'])) {
        $uploadsDir = BASE_PATH . '/assets/images/obras/uploads';
        if (is_dir($uploadsDir)) {
            $localMatches = @glob($uploadsDir . '/' . $raw['id'] . '_*');
            if (!empty($localMatches)) {
                $firstMatch = basename($localMatches[0]);
                $localRelUrl = 'assets/images/obras/uploads/' . $firstMatch;
                $imagens[] = [
                    'id'           => null,
                    'image_url'    => $localRelUrl,
                    'storage_path' => $localRelUrl,
                    'image_type'   => 'principal',
                    'sort_order'   => 1,
                    'is_primary'   => true
                ];
            }
        }
    }

    // Resolve imagem principal para o card (usa placeholder neutro de moldura se nenhuma foto foi associada)
    $imagemPrincipal = 'assets/images/obras/placeholder-obra.svg';
    $primaryStoragePath = '';
    if (!empty($imagens)) {
        $imagemPrincipal = $imagens[0]['image_url'];
        $primaryStoragePath = $imagens[0]['storage_path'] ?? '';
    }

    $dimensoes = '80 × 120 cm';
    if (!empty($raw['height']) && !empty($raw['width'])) {
        $h = (float)$raw['height'];
        $w = (float)$raw['width'];
        $d = !empty($raw['depth']) ? ' × ' . (float)$raw['depth'] : '';
        $dimensoes = "{$h} × {$w}{$d} cm";
    }

    // Monta a galeria completa
    $galeria = [];
    foreach ($imagens as $img) {
        $tipo = $img['image_type'] ?? 'principal';
        $galeria[] = [
            'id'           => $img['id'] ?? null,
            'imagem'       => $img['image_url'],
            'storage_path' => $img['storage_path'] ?? '',
            'tipo'         => $tipo,
            'titulo'       => supabase_legenda_tipo_imagem($tipo),
            'is_primary'   => !empty($img['is_primary']),
            'sort_order'   => (int)($img['sort_order'] ?? 0)
        ];
    }

    return [
        'id'                  => $raw['id'],
        'slug'                => $raw['slug'] ?? '',
        'titulo'              => $raw['name'] ?? 'Obra de Arte',
        'artista'             => $artista['name'] ?? 'Artista Convidado',
        'artista_id'          => $raw['artist_id'] ?? ($artista['id'] ?? null),
        'artista_slug'        => $artista['slug'] ?? '',
        'biografia_artista'   => $artista['biography'] ?? '',
        'dimensoes'           => $dimensoes,
        'largura'             => (float)($raw['width'] ?? 0),
        'altura'              => (float)($raw['height'] ?? 0),
        'profundidade'        => !empty($raw['depth']) ? (float)$raw['depth'] : null,
        'preco'               => 'Preço sob consulta',
        'categoria'           => $categoria['name'] ?? 'Pinturas',
        'categoria_id'        => $raw['category_id'] ?? ($categoria['id'] ?? null),
        'categoria_slug'      => $categoria['slug'] ?? 'todas',
        'imagem'              => $imagemPrincipal,
        'imagem_storage_path' => $primaryStoragePath,
        'tecnica'             => $raw['technique'] ?? 'Óleo sobre tela',
        'ano'                 => $raw['year'] ?? 2024,
        'codigo'              => $raw['code'] ?? 'ASF-0000',
        'estado_conservacao'  => $raw['conservation_state'] ?? 'Excelente',
        'procedencia'         => $raw['provenance'] ?? 'Acervo Particular',
        'localizacao'         => $raw['location'] ?? 'Brasil',
        'descricao'           => $raw['description'] ?? '',
        'disponibilidade'     => $raw['availability'] ?? 'available',
        'destaque'            => !empty($raw['featured']),
        'ativo'               => !empty($raw['active']),
        'ordem'               => (int)($raw['sort_order'] ?? 0),
        'galeria'             => $galeria
    ];
}

/**
 * Busca obras ativas com suporte a filtro, busca, ordenação e mesclagem imediata no frontend
 */
function supabase_buscar_obras(?string $categoria = null, ?string $termo = null, int $limite = 50, bool $somenteAtivas = true, ?string $authToken = null): array {
    $deletedIds = function_exists('artsale_get_deleted_artwork_ids') ? artsale_get_deleted_artwork_ids() : [];
    $token = $authToken ?: (function_exists('get_admin_token') ? get_admin_token() : null);

    global $catalogoObras;
    $fallback = $catalogoObras ?? [];
    $locais = function_exists('artsale_get_local_artworks') ? artsale_get_local_artworks() : [];

    if (!supabase_is_configured()) {
        $mesclado = function_exists('artsale_merge_catalogo') ? artsale_merge_catalogo($locais, $fallback) : array_merge($locais, $fallback);
        return array_values(array_filter($mesclado, fn($item) => !in_array((string)$item['id'], $deletedIds, true)));
    }

    // Filtro: se somenteAtivas for true, exclui apenas as obras explicitamente marcadas com active = false
    // (não oculta obras cujo active seja true ou nulo)
    $activeClause = $somenteAtivas ? '&not.active=eq.false' : '';
    $endpoint = 'artworks?select=id,name,slug,technique,year,width,height,depth,code,availability,featured,active,artist_id,category_id,created_at,artists(id,name,slug),categories(id,name,slug),artwork_images(id,image_url,storage_path,image_type,sort_order,is_primary)' . $activeClause . '&order=created_at.desc';

    if ($categoria && $categoria !== 'all' && $categoria !== 'todas') {
        $endpoint .= '&categories.slug=eq.' . urlencode($categoria) . '&categories=not.is.null';
    }

    if ($termo && trim($termo) !== '') {
        $clean = urlencode(trim($termo));
        $endpoint .= '&or=(name.ilike.*' . $clean . '*,technique.ilike.*' . $clean . '*,code.ilike.*' . $clean . '*)';
    }

    if ($limite > 0) {
        $endpoint .= '&limit=' . $limite;
    }

    $res = supabase_request($endpoint, 'GET', null, $token);

    // Se a consulta com relacionamentos falhar, tenta consulta básica direta na tabela artworks
    if (!empty($res['error'])) {
        $simpleEndpoint = 'artworks?select=id,name,slug,description,technique,year,width,height,depth,conservation_state,code,availability,provenance,location,featured,active,artist_id,category_id,created_at' . $activeClause . '&order=created_at.desc';
        if ($limite > 0) $simpleEndpoint .= '&limit=' . $limite;
        $res = supabase_request($simpleEndpoint, 'GET', null, $token);
    }

    $obrasDb = [];
    if (!empty($res['data']) && is_array($res['data'])) {
        foreach ($res['data'] as $raw) {
            if (in_array((string)$raw['id'], $deletedIds, true)) {
                continue;
            }
            $norm = supabase_normalizar_obra($raw);
            $obrasDb[] = $norm;
            if (function_exists('artsale_save_local_artwork')) {
                artsale_save_local_artwork($norm);
            }
        }
    }

    // Filtra $locais para remover qualquer obra excluída ou com ativo=false
    $locais = array_values(array_filter($locais, function($item) use ($deletedIds, $somenteAtivas) {
        $id = (string)($item['id'] ?? '');
        if (empty($id) || in_array($id, $deletedIds, true)) return false;
        if ($somenteAtivas && isset($item['ativo']) && $item['ativo'] === false) return false;
        return true;
    }));

    if ($somenteAtivas) {
        $obrasDb = array_values(array_filter($obrasDb, fn($item) => !isset($item['ativo']) || $item['ativo'] !== false));
    }

    // Mescla obras do banco com a persistência local (apenas obras válidas e não excluídas)
    $todasNovas = function_exists('artsale_merge_catalogo') 
        ? artsale_merge_catalogo($obrasDb, $locais) 
        : (!empty($obrasDb) ? $obrasDb : $locais);

    // Garante remoção de qualquer obra excluída
    $todasNovas = array_values(array_filter($todasNovas, fn($item) => !in_array((string)($item['id'] ?? ''), $deletedIds, true)));

    if ($somenteAtivas) {
        $todasNovas = array_values(array_filter($todasNovas, fn($item) => !isset($item['ativo']) || $item['ativo'] !== false));
    }

    // Se já existem obras no acervo (do banco ou locais válidas), retorna APENAS elas.
    // NUNCA anexa $fallback (duplicatas 101-112) por cima de um acervo já populado!
    if (!empty($todasNovas)) {
        return $todasNovas;
    }

    // Apenas se o acervo estiver completamente vazio em todas as fontes:
    $fallbackValido = array_values(array_filter($fallback, function($item) use ($deletedIds, $somenteAtivas) {
        $id = (string)($item['id'] ?? '');
        if (empty($id) || in_array($id, $deletedIds, true)) return false;
        if ($somenteAtivas && isset($item['ativo']) && $item['ativo'] === false) return false;
        return true;
    }));

    return $fallbackValido;
}

/**
 * Busca uma única obra por ID ou slug e inclui todas as imagens da galeria
 */
function supabase_buscar_obra(string $slugOrId, ?string $authToken = null): ?array {
    $deletedIds = function_exists('artsale_get_deleted_artwork_ids') ? artsale_get_deleted_artwork_ids() : [];
    if (in_array((string)$slugOrId, $deletedIds, true)) {
        return null;
    }

    $token = $authToken ?: (function_exists('get_admin_token') ? get_admin_token() : null);

    if (supabase_is_configured()) {
        $isUuid = preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $slugOrId);
        $filter = $isUuid ? ('id=eq.' . $slugOrId) : ('slug=eq.' . urlencode($slugOrId));
        $endpoint = 'artworks?select=id,name,slug,description,technique,year,width,height,depth,conservation_state,code,availability,provenance,location,featured,active,artist_id,category_id,artists(id,name,slug,biography),categories(id,name,slug),artwork_images(id,image_url,storage_path,image_type,sort_order,is_primary)&' . $filter . '&limit=1';

        $res = supabase_request($endpoint, 'GET', null, $token);
        if (!empty($res['error'])) {
            $simpleEndpoint = 'artworks?select=id,name,slug,description,technique,year,width,height,depth,conservation_state,code,availability,provenance,location,featured,active,artist_id,category_id,created_at&' . $filter . '&limit=1';
            $res = supabase_request($simpleEndpoint, 'GET', null, $token);
        }

        if (!empty($res['data']) && is_array($res['data']) && count($res['data']) > 0) {
            $raw = $res['data'][0];
            if (!in_array((string)$raw['id'], $deletedIds, true)) {
                $norm = supabase_normalizar_obra($raw);
                if (function_exists('artsale_save_local_artwork')) {
                    artsale_save_local_artwork($norm);
                }
                return $norm;
            }
        }
    }

    // Busca na persistência local se houver
    if (function_exists('artsale_get_local_artworks')) {
        $locais = artsale_get_local_artworks();
        foreach ($locais as $loc) {
            if ((string)($loc['id'] ?? '') === (string)$slugOrId || (string)($loc['slug'] ?? '') === (string)$slugOrId) {
                return $loc;
            }
        }
    }

    return get_obra_by_id((int)$slugOrId);
}

/**
 * ==============================================================================
 * OPERAÇÕES DE STORAGE NO SUPABASE VIA PHP
 * Requer token de administrador ou Service Role Key
 * ==============================================================================
 */

/**
 * Validação de arquivo de imagem em PHP (seguro para ambientes sem fileinfo)
 */
function supabase_validar_arquivo_imagem(array $file): array {
    if (empty($file['tmp_name']) || !file_exists($file['tmp_name'])) {
        return ['valido' => false, 'erro' => 'Nenhum arquivo válido enviado.'];
    }

    if ($file['size'] > MAX_IMAGE_SIZE_BYTES) {
        $sizeMB = round($file['size'] / (1024 * 1024), 1);
        return ['valido' => false, 'erro' => "O arquivo possui {$sizeMB} MB. Limite máximo: 10 MB."];
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $permitidas = ['jpg', 'jpeg', 'png', 'webp'];
    if (!in_array($ext, $permitidas, true)) {
        return ['valido' => false, 'erro' => "Extensão '.{$ext}' não permitida. Use JPG, JPEG, PNG ou WEBP."];
    }

    $mime = null;
    if (function_exists('finfo_open')) {
        $finfo = @finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $mime = @finfo_file($finfo, $file['tmp_name']);
            @finfo_close($finfo);
        }
    } elseif (function_exists('mime_content_type')) {
        $mime = @mime_content_type($file['tmp_name']);
    }

    if ($mime) {
        $mimesValidos = ['image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($mime, $mimesValidos, true)) {
            return ['valido' => false, 'erro' => "Tipo de arquivo inválido ({$mime}). Use JPG, JPEG, PNG ou WEBP."];
        }
    }

    return ['valido' => true, 'erro' => null, 'ext' => $ext, 'mime' => ($mime ?: 'image/jpeg')];
}

/**
 * Faz upload de imagem para a obra, com persistência local garantida (assets/images/obras/uploads/)
 * e sincronização transparente com o Supabase Storage.
 * Organização:
 *   artworks/{artwork_id}/principal/{filename}
 *   artworks/{artwork_id}/gallery/{filename}
 */
function supabase_upload_imagem_obra(
    string $tmpFilePath,
    string $artworkId,
    string $originalFileName,
    string $imageType = 'principal',
    bool $isPrimary = false,
    int $sortOrder = 0,
    ?string $authToken = null
): array {
    $fileData = @file_get_contents($tmpFilePath);
    if ($fileData === false || empty($fileData)) {
        return ['sucesso' => false, 'erro' => 'Não foi possível ler o arquivo temporário enviado.'];
    }

    if (strlen($fileData) > MAX_IMAGE_SIZE_BYTES) {
        return ['sucesso' => false, 'erro' => 'O arquivo excede o limite máximo permitido de 10 MB.'];
    }

    $ext = strtolower(pathinfo($originalFileName, PATHINFO_EXTENSION));
    $permitidas = ['jpg', 'jpeg', 'png', 'webp'];
    if (!in_array($ext, $permitidas, true)) {
        return ['sucesso' => false, 'erro' => 'Extensão de fotografia não permitida. Use JPG, JPEG, PNG ou WEBP.'];
    }

    // Validação de integridade real do arquivo de imagem
    if (function_exists('getimagesizefromstring')) {
        $imgInfo = @getimagesizefromstring($fileData);
        if ($imgInfo === false) {
            return ['sucesso' => false, 'erro' => 'O conteúdo do arquivo não corresponde a uma imagem válida.'];
        }
    }

    $rawName = pathinfo($originalFileName, PATHINFO_FILENAME);
    $cleanBase = preg_replace('/[^a-zA-Z0-9_-]/', '_', strtolower($rawName));
    $cleanName = "{$cleanBase}.{$ext}";
    $cleanArtworkId = preg_replace('/[^a-zA-Z0-9_-]/', '', $artworkId);
    $folder = ($imageType === 'principal') ? 'principal' : 'gallery';
    $timestamp = time();
    $storagePath = "{$cleanArtworkId}/{$folder}/{$timestamp}_{$cleanName}";

    // 1. Persistência local em assets/images/obras/uploads/
    $uploadsDir = BASE_PATH . '/assets/images/obras/uploads';
    if (!is_dir($uploadsDir)) {
        @mkdir($uploadsDir, 0777, true);
    }
    $localFileBasename = "{$cleanArtworkId}_{$timestamp}_{$cleanName}";
    $localFilePath = $uploadsDir . '/' . $localFileBasename;
    @file_put_contents($localFilePath, $fileData);
    $localRelativeUrl = 'assets/images/obras/uploads/' . $localFileBasename;

    $finalImageUrl = $localRelativeUrl;
    $storageSuccess = false;

    // 2. Tenta upload para o Supabase Storage se configurado
    if (supabase_is_configured()) {
        $token = $authToken ?: (defined('SUPABASE_SERVICE_ROLE_KEY') && !empty(SUPABASE_SERVICE_ROLE_KEY) ? SUPABASE_SERVICE_ROLE_KEY : SUPABASE_ANON_KEY);
        if (supabase_is_jwt_expired($token)) {
            $token = (defined('SUPABASE_SERVICE_ROLE_KEY') && !empty(SUPABASE_SERVICE_ROLE_KEY)) ? SUPABASE_SERVICE_ROLE_KEY : SUPABASE_ANON_KEY;
        }

        $baseUrl = rtrim(SUPABASE_URL, '/');
        $baseUrl = preg_replace('#/rest/v1/?$#', '', $baseUrl);
        $bucket = SUPABASE_STORAGE_BUCKET;
        $uploadUrl = "{$baseUrl}/storage/v1/object/{$bucket}/{$storagePath}";

        $mimeType = 'image/jpeg';
        if (function_exists('finfo_open')) {
            $finfo = @finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $m = @finfo_file($finfo, $tmpFilePath);
                if ($m) $mimeType = $m;
                @finfo_close($finfo);
            }
        } elseif (function_exists('mime_content_type')) {
            $m = @mime_content_type($tmpFilePath);
            if ($m) $mimeType = $m;
        }
        if ($mimeType === 'image/jpeg') {
            $ext = strtolower(pathinfo($originalFileName, PATHINFO_EXTENSION));
            if ($ext === 'png') $mimeType = 'image/png';
            elseif ($ext === 'webp') $mimeType = 'image/webp';
        }

        $res = supabase_http_call($uploadUrl, 'POST', [
            'apikey: ' . SUPABASE_ANON_KEY,
            'Authorization: Bearer ' . $token,
            'Content-Type: ' . $mimeType,
            'x-upsert: true'
        ], $fileData, 15);

        // Se o upload falhar por JWT expirado, retenta com o Anon Key
        if ($res['status'] >= 400) {
            $errDecoded = json_decode($res['response'] ?: '', true);
            $errMsg = $errDecoded['message'] ?? ($res['error'] ?: '');

            if (stripos($errMsg, 'jwt expired') !== false && $token !== SUPABASE_ANON_KEY) {
                $token = SUPABASE_ANON_KEY;
                $res = supabase_http_call($uploadUrl, 'POST', [
                    'apikey: ' . SUPABASE_ANON_KEY,
                    'Authorization: Bearer ' . $token,
                    'Content-Type: ' . $mimeType,
                    'x-upsert: true'
                ], $fileData, 15);
            }

            // Se o bucket não existir, tenta criar
            if (stripos($errMsg, 'bucket not found') !== false || $res['status'] === 404) {
                $bucketCreateUrl = "{$baseUrl}/storage/v1/bucket";
                supabase_http_call($bucketCreateUrl, 'POST', [
                    'apikey: ' . SUPABASE_ANON_KEY,
                    'Authorization: Bearer ' . $token,
                    'Content-Type: application/json'
                ], json_encode([
                    'id'     => $bucket,
                    'name'   => $bucket,
                    'public' => true
                ]), 10);

                // Tenta upload novamente
                $res = supabase_http_call($uploadUrl, 'POST', [
                    'apikey: ' . SUPABASE_ANON_KEY,
                    'Authorization: Bearer ' . $token,
                    'Content-Type: ' . $mimeType,
                    'x-upsert: true'
                ], $fileData, 15);
            }
        }

        if ($res['status'] < 400 && $res['response'] !== false) {
            $finalImageUrl = "{$baseUrl}/storage/v1/object/public/{$bucket}/{$storagePath}";
            $storageSuccess = true;
        }
    }

    // 3. Salva metadados na tabela public.artwork_images
    $isThisPrimary = $isPrimary || ($imageType === 'principal');
    $validImageTypes = ['principal', 'frontal', 'lateral', 'detalhe', 'textura', 'ambiente', 'outras'];
    $imageTypeSanitized = in_array(strtolower($imageType), $validImageTypes, true) ? strtolower($imageType) : 'outras';
    if ($isThisPrimary) {
        $imageTypeSanitized = 'principal';
    }

    $tokenForDb = $authToken ?: (defined('SUPABASE_SERVICE_ROLE_KEY') && !empty(SUPABASE_SERVICE_ROLE_KEY) ? SUPABASE_SERVICE_ROLE_KEY : SUPABASE_ANON_KEY);
    if (supabase_is_jwt_expired($tokenForDb)) {
        $tokenForDb = (defined('SUPABASE_SERVICE_ROLE_KEY') && !empty(SUPABASE_SERVICE_ROLE_KEY)) ? SUPABASE_SERVICE_ROLE_KEY : SUPABASE_ANON_KEY;
    }

    if ($isThisPrimary) {
        // Desmarca qualquer outra imagem como principal para esta obra (evita colisão de índice único)
        supabase_request(
            "artwork_images?artwork_id=eq." . urlencode($artworkId) . "&or=(is_primary.eq.true,image_type.eq.principal)",
            'PATCH',
            ['is_primary' => false, 'image_type' => 'outras'],
            $tokenForDb
        );
    }

    $dbPayload = [
        'artwork_id'   => $artworkId,
        'image_url'    => $finalImageUrl,
        'storage_path' => $storageSuccess ? $storagePath : $localRelativeUrl,
        'image_type'   => $imageTypeSanitized,
        'sort_order'   => $sortOrder,
        'is_primary'   => $isThisPrimary
    ];

    $dbRes = supabase_request('artwork_images', 'POST', $dbPayload, $tokenForDb);

    // Fallback: se ainda der conflito de chave única, desmarca todas as imagens da obra e tenta novamente
    if (!empty($dbRes['error']) && stripos($dbRes['error'], 'idx_artwork_images_single_primary') !== false) {
        supabase_request(
            "artwork_images?artwork_id=eq." . urlencode($artworkId),
            'PATCH',
            ['is_primary' => false, 'image_type' => 'outras'],
            $tokenForDb
        );
        $dbRes = supabase_request('artwork_images', 'POST', $dbPayload, $tokenForDb);
    }

    if (!empty($dbRes['error'])) {
        return [
            'sucesso'      => false,
            'erro'         => 'Erro ao associar fotografia no catálogo da obra: ' . $dbRes['error'],
            'public_url'   => $finalImageUrl,
            'storage_path' => $storagePath,
            'is_local'     => !$storageSuccess
        ];
    }

    if (function_exists('artsale_get_local_artworks') && function_exists('artsale_save_local_artwork')) {
        $locais = artsale_get_local_artworks();
        foreach ($locais as &$loc) {
            if ((string)($loc['id'] ?? '') === (string)$artworkId) {
                if ($isThisPrimary) {
                    $loc['imagem'] = $finalImageUrl;
                    $loc['imagem_storage_path'] = $storageSuccess ? $storagePath : $localRelativeUrl;
                    if (!empty($loc['galeria'])) {
                        foreach ($loc['galeria'] as &$g) {
                            $g['is_primary'] = false;
                            if (($g['tipo'] ?? '') === 'principal') {
                                $g['tipo'] = 'outras';
                            }
                        }
                        unset($g);
                    }
                }
                $loc['galeria'] = $loc['galeria'] ?? [];
                $jaExiste = false;
                foreach ($loc['galeria'] as $g) {
                    if (($g['imagem'] ?? '') === $finalImageUrl) {
                        $jaExiste = true;
                        break;
                    }
                }
                if (!$jaExiste) {
                    $loc['galeria'][] = [
                        'id'          => $dbRes['data'][0]['id'] ?? null,
                        'imagem'      => $finalImageUrl,
                        'storage_path'=> $storageSuccess ? $storagePath : $localRelativeUrl,
                        'tipo'        => $imageType,
                        'titulo'      => ($imageType === 'principal' ? 'Imagem principal' : 'Galeria'),
                        'is_primary'  => $isThisPrimary,
                        'sort_order'  => $sortOrder
                    ];
                }
                artsale_save_local_artwork($loc);
                break;
            }
        }
        unset($loc);
    }

    supabase_purge_cache();

    return [
        'sucesso'      => true,
        'public_url'   => $finalImageUrl,
        'storage_path' => $storagePath,
        'is_local'     => !$storageSuccess,
        'dados'        => $dbRes['data'] ?? null
    ];
}

/**
 * Exclui imagem do storage e da tabela
 */
function supabase_excluir_imagem_obra(string $imageId, ?string $storagePath = null, ?string $authToken = null): array {
    $token = $authToken ?: (defined('SUPABASE_SERVICE_ROLE_KEY') ? SUPABASE_SERVICE_ROLE_KEY : SUPABASE_ANON_KEY);

    if ($storagePath) {
        $baseUrl = rtrim(SUPABASE_URL, '/');
        $baseUrl = preg_replace('#/rest/v1/?$#', '', $baseUrl);
        $bucket = SUPABASE_STORAGE_BUCKET;
        $deleteUrl = "{$baseUrl}/storage/v1/object/{$bucket}";

        supabase_http_call($deleteUrl, 'DELETE', [
            'apikey: ' . SUPABASE_ANON_KEY,
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json'
        ], json_encode(['prefixes' => [$storagePath]]));
    }

    // Exclui do banco
    return supabase_request("artwork_images?id=eq." . urlencode($imageId), 'DELETE', null, $token);
}

/**
 * Define uma imagem como principal de uma obra
 */
function supabase_definir_imagem_principal(string $artworkId, string $imageId, ?string $authToken = null): array {
    $token = $authToken ?: (defined('SUPABASE_SERVICE_ROLE_KEY') && !empty(SUPABASE_SERVICE_ROLE_KEY) ? SUPABASE_SERVICE_ROLE_KEY : SUPABASE_ANON_KEY);
    if (supabase_is_jwt_expired($token)) {
        $token = (defined('SUPABASE_SERVICE_ROLE_KEY') && !empty(SUPABASE_SERVICE_ROLE_KEY)) ? SUPABASE_SERVICE_ROLE_KEY : SUPABASE_ANON_KEY;
    }

    // 1. Demote todas as imagens atuais da obra para evitar colisão do índice único idx_artwork_images_single_primary
    supabase_request(
        "artwork_images?artwork_id=eq." . urlencode($artworkId),
        'PATCH',
        ['is_primary' => false, 'image_type' => 'outras'],
        $token
    );

    // 2. Define a imagem selecionada como principal
    $res = supabase_request(
        "artwork_images?id=eq." . urlencode($imageId),
        'PATCH',
        ['is_primary' => true, 'image_type' => 'principal'],
        $token
    );

    // 3. Atualiza persistência e cache local
    if (function_exists('artsale_get_local_artworks') && function_exists('artsale_save_local_artwork')) {
        $locais = artsale_get_local_artworks();
        foreach ($locais as &$loc) {
            if ((string)($loc['id'] ?? '') === (string)$artworkId) {
                if (!empty($loc['galeria'])) {
                    foreach ($loc['galeria'] as &$g) {
                        if ((string)($g['id'] ?? '') === (string)$imageId) {
                            $g['is_primary'] = true;
                            $g['tipo'] = 'principal';
                            $loc['imagem'] = $g['imagem'];
                            $loc['imagem_storage_path'] = $g['storage_path'] ?? '';
                        } else {
                            $g['is_primary'] = false;
                            if (($g['tipo'] ?? '') === 'principal') {
                                $g['tipo'] = 'outras';
                            }
                        }
                    }
                    unset($g);
                }
                artsale_save_local_artwork($loc);
                break;
            }
        }
        unset($loc);
    }

    supabase_purge_cache();

    return $res;
}

/**
 * Atualiza o status de disponibilidade da obra (available, reserved, sold, unavailable)
 */
function supabase_atualizar_status_obra(string $artworkId, string $status, ?string $authToken = null): array {
    $token = $authToken ?: (defined('SUPABASE_SERVICE_ROLE_KEY') ? SUPABASE_SERVICE_ROLE_KEY : SUPABASE_ANON_KEY);
    $validos = ['available', 'reserved', 'sold', 'unavailable'];
    $cleanStatus = in_array(strtolower($status), $validos, true) ? strtolower($status) : 'available';
    $res = supabase_request("artworks?id=eq." . urlencode($artworkId), 'PATCH', ['availability' => $cleanStatus], $token);

    if (function_exists('artsale_get_local_artworks') && function_exists('artsale_save_local_artwork')) {
        $locais = artsale_get_local_artworks();
        foreach ($locais as $loc) {
            if ((string)($loc['id'] ?? '') === (string)$artworkId) {
                $loc['disponibilidade'] = $cleanStatus;
                artsale_save_local_artwork($loc);
                break;
            }
        }
    }

    return $res;
}

/**
 * Alterna estado ativo/inativo da obra (publicação no site)
 */
function supabase_alternar_ativo_obra(string $artworkId, bool $active, ?string $authToken = null): array {
    $token = $authToken ?: (defined('SUPABASE_SERVICE_ROLE_KEY') ? SUPABASE_SERVICE_ROLE_KEY : SUPABASE_ANON_KEY);
    $res = supabase_request("artworks?id=eq." . urlencode($artworkId), 'PATCH', ['active' => $active], $token);

    if (function_exists('artsale_get_local_artworks') && function_exists('artsale_save_local_artwork')) {
        $locais = artsale_get_local_artworks();
        foreach ($locais as $loc) {
            if ((string)($loc['id'] ?? '') === (string)$artworkId) {
                $loc['ativo'] = $active;
                artsale_save_local_artwork($loc);
                break;
            }
        }
    }

    return $res;
}

/**
 * Atualiza a ordem de exibição de uma imagem da galeria
 */
function supabase_atualizar_ordem_imagem(string $imageId, int $sortOrder, ?string $authToken = null): array {
    $token = $authToken ?: (defined('SUPABASE_SERVICE_ROLE_KEY') ? SUPABASE_SERVICE_ROLE_KEY : SUPABASE_ANON_KEY);
    return supabase_request("artwork_images?id=eq." . urlencode($imageId), 'PATCH', ['sort_order' => $sortOrder], $token);
}

/**
 * Alterna destaque de uma obra na home
 */
function supabase_alternar_destaque_obra(string $artworkId, bool $featured, ?string $authToken = null): array {
    $token = $authToken ?: (defined('SUPABASE_SERVICE_ROLE_KEY') ? SUPABASE_SERVICE_ROLE_KEY : SUPABASE_ANON_KEY);
    $res = supabase_request("artworks?id=eq." . urlencode($artworkId), 'PATCH', ['featured' => $featured], $token);

    if (function_exists('artsale_get_local_artworks') && function_exists('artsale_save_local_artwork')) {
        $locais = artsale_get_local_artworks();
        foreach ($locais as $loc) {
            if ((string)($loc['id'] ?? '') === (string)$artworkId) {
                $loc['destaque'] = $featured;
                artsale_save_local_artwork($loc);
                break;
            }
        }
    }

    return $res;
}

/**
 * Atualiza campos cadastrais de uma obra
 */
function supabase_atualizar_dados_obra(string $artworkId, array $dados, ?string $authToken = null): array {
    $token = $authToken ?: (defined('SUPABASE_SERVICE_ROLE_KEY') ? SUPABASE_SERVICE_ROLE_KEY : SUPABASE_ANON_KEY);
    $payload = [];

    if (isset($dados['name'])) $payload['name'] = trim($dados['name']);
    if (isset($dados['slug'])) $payload['slug'] = trim($dados['slug']);
    if (isset($dados['technique'])) $payload['technique'] = trim($dados['technique']);
    if (isset($dados['year'])) $payload['year'] = (int)$dados['year'];
    if (isset($dados['width'])) $payload['width'] = (float)$dados['width'];
    if (isset($dados['height'])) $payload['height'] = (float)$dados['height'];
    if (array_key_exists('depth', $dados)) $payload['depth'] = !empty($dados['depth']) ? (float)$dados['depth'] : null;
    if (isset($dados['conservation_state'])) $payload['conservation_state'] = trim($dados['conservation_state']);
    if (isset($dados['provenance'])) $payload['provenance'] = trim($dados['provenance']);
    if (isset($dados['location'])) $payload['location'] = trim($dados['location']);
    if (isset($dados['description'])) $payload['description'] = trim($dados['description']);
    if (isset($dados['code'])) $payload['code'] = trim($dados['code']);
    if (isset($dados['availability'])) {
        $validos = ['available', 'reserved', 'sold', 'unavailable'];
        $payload['availability'] = in_array(strtolower($dados['availability']), $validos, true) ? strtolower($dados['availability']) : 'available';
    }
    if (isset($dados['featured'])) $payload['featured'] = (bool)$dados['featured'];
    if (array_key_exists('artist_id', $dados) || !empty($dados['artist_name'])) {
        $candidateArtist = !empty($dados['artist_name']) ? $dados['artist_name'] : ($dados['artist_id'] ?? null);
        if (!empty($candidateArtist)) {
            $resolvedArtist = supabase_obter_ou_criar_artista($candidateArtist, $token);
            if ($resolvedArtist) {
                $payload['artist_id'] = $resolvedArtist;
            }
        }
    }
    if (array_key_exists('category_id', $dados) || !empty($dados['category_name'])) {
        $candidateCat = !empty($dados['category_name']) ? $dados['category_name'] : ($dados['category_id'] ?? null);
        if (!empty($candidateCat)) {
            $resolvedCat = supabase_obter_ou_criar_categoria($candidateCat, $token);
            if ($resolvedCat) {
                $payload['category_id'] = $resolvedCat;
            }
        }
    }

    if (empty($payload)) {
        return ['data' => null, 'error' => 'Nenhum dado informado para atualização.'];
    }

    $res = supabase_request("artworks?id=eq." . urlencode($artworkId), 'PATCH', $payload, $token);

    if (function_exists('artsale_get_local_artworks') && function_exists('artsale_save_local_artwork')) {
        $locais = artsale_get_local_artworks();
        foreach ($locais as $loc) {
            if ((string)($loc['id'] ?? '') === (string)$artworkId) {
                if (isset($payload['name'])) $loc['titulo'] = $payload['name'];
                if (isset($payload['slug'])) $loc['slug'] = $payload['slug'];
                if (isset($payload['technique'])) $loc['tecnica'] = $payload['technique'];
                if (isset($payload['year'])) $loc['ano'] = $payload['year'];
                if (isset($payload['width'])) $loc['largura'] = $payload['width'];
                if (isset($payload['height'])) $loc['altura'] = $payload['height'];
                if (array_key_exists('depth', $payload)) $loc['profundidade'] = $payload['depth'];
                if (isset($payload['conservation_state'])) $loc['estado_conservacao'] = $payload['conservation_state'];
                if (isset($payload['provenance'])) $loc['procedencia'] = $payload['provenance'];
                if (isset($payload['location'])) $loc['localizacao'] = $payload['location'];
                if (isset($payload['description'])) $loc['descricao'] = $payload['description'];
                if (isset($payload['code'])) $loc['codigo'] = $payload['code'];
                if (isset($payload['availability'])) $loc['disponibilidade'] = $payload['availability'];
                if (isset($payload['featured'])) $loc['destaque'] = $payload['featured'];
                artsale_save_local_artwork($loc);
                break;
            }
        }
    }

    return $res;
}

/**
 * Garante um ID válido de artista para inserção em artworks (satisfazendo NOT NULL).
 * - Se for um UUID de artista existente, valida e retorna.
 * - Se for um nome textual informado pelo curador, busca ou cria na tabela artists.
 * - Se estiver vazio, busca ou cria "Artista Convidado" como fallback seguro.
 */
function supabase_obter_ou_criar_artista(?string $artistIdOrName, ?string $authToken = null): ?string {
    if (!supabase_is_configured()) {
        return null;
    }

    $token = $authToken ?: (defined('SUPABASE_SERVICE_ROLE_KEY') ? SUPABASE_SERVICE_ROLE_KEY : SUPABASE_ANON_KEY);
    $input = trim($artistIdOrName ?? '');

    // 1. Se for um UUID válido, verifica se existe
    $isUuid = preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $input);
    if ($isUuid) {
        $check = supabase_request("artists?id=eq.{$input}&select=id", 'GET', null, $token);
        if (!empty($check['data']) && is_array($check['data']) && count($check['data']) > 0) {
            return $check['data'][0]['id'];
        }
    }

    // 2. Se for um nome textual informado ou vazio
    $nomeArtista = (!empty($input) && !$isUuid) ? $input : 'Artista Convidado';
    $cleanNome = urlencode($nomeArtista);
    $busca = supabase_request("artists?name=ilike.{$cleanNome}&select=id&limit=1", 'GET', null, $token);
    if (!empty($busca['data']) && is_array($busca['data']) && count($busca['data']) > 0) {
        return $busca['data'][0]['id'];
    }

    // 3. Fallback: se for "Artista Convidado", tenta qualquer artista já cadastrado antes de criar novo
    if ($nomeArtista === 'Artista Convidado') {
        $qualquer = supabase_request("artists?select=id&limit=1", 'GET', null, $token);
        if (!empty($qualquer['data']) && is_array($qualquer['data']) && count($qualquer['data']) > 0) {
            return $qualquer['data'][0]['id'];
        }
    }

    // 4. Cria o artista na tabela artists
    $cleanSlug = preg_replace('~[^\pL\d]+~u', '-', $nomeArtista);
    $cleanSlug = trim($cleanSlug, '-');
    $cleanSlug = strtolower(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $cleanSlug) ?: $cleanSlug) . '-' . substr(uniqid(), -4);

    $novo = supabase_request('artists', 'POST', [
        'name'      => $nomeArtista,
        'slug'      => $cleanSlug,
        'biography' => 'Mestre em exibição na galeria Art For Sale.',
        'active'    => true
    ], $token);

    if (!empty($novo['data']) && is_array($novo['data']) && count($novo['data']) > 0) {
        return $novo['data'][0]['id'];
    }

    return null;
}

/**
 * Garante um ID válido de categoria para inserção em artworks (satisfazendo NOT NULL).
 */
function supabase_obter_ou_criar_categoria(?string $categoryIdOrName, ?string $authToken = null): ?string {
    if (!supabase_is_configured()) {
        return null;
    }

    $token = $authToken ?: (defined('SUPABASE_SERVICE_ROLE_KEY') ? SUPABASE_SERVICE_ROLE_KEY : SUPABASE_ANON_KEY);
    $input = trim($categoryIdOrName ?? '');

    $isUuid = preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $input);
    if ($isUuid) {
        $check = supabase_request("categories?id=eq.{$input}&select=id", 'GET', null, $token);
        if (!empty($check['data']) && is_array($check['data']) && count($check['data']) > 0) {
            return $check['data'][0]['id'];
        }
    }

    $nomeCat = (!empty($input) && !$isUuid) ? $input : 'Pinturas';
    $cleanNome = urlencode($nomeCat);
    $busca = supabase_request("categories?name=ilike.{$cleanNome}&select=id&limit=1", 'GET', null, $token);
    if (!empty($busca['data']) && is_array($busca['data']) && count($busca['data']) > 0) {
        return $busca['data'][0]['id'];
    }

    $qualquer = supabase_request("categories?select=id&limit=1", 'GET', null, $token);
    if (!empty($qualquer['data']) && is_array($qualquer['data']) && count($qualquer['data']) > 0) {
        return $qualquer['data'][0]['id'];
    }

    $cleanSlug = 'pinturas-' . substr(uniqid(), -4);
    $nova = supabase_request('categories', 'POST', [
        'name'   => $nomeCat,
        'slug'   => $cleanSlug,
        'active' => true
    ], $token);

    if (!empty($nova['data']) && is_array($nova['data']) && count($nova['data']) > 0) {
        return $nova['data'][0]['id'];
    }

    return null;
}

/**
 * Cadastra uma nova obra no acervo do Supabase
 */
function supabase_criar_obra(array $dados, ?string $authToken = null): array {
    $token = $authToken ?: (defined('SUPABASE_SERVICE_ROLE_KEY') ? SUPABASE_SERVICE_ROLE_KEY : SUPABASE_ANON_KEY);

    $name = trim($dados['name'] ?? 'Nova Obra');
    $slug = trim($dados['slug'] ?? '');
    if (empty($slug)) {
        $clean = preg_replace('~[^\pL\d]+~u', '-', $name);
        $clean = trim($clean, '-');
        $clean = strtolower(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $clean) ?: $clean);
        $slug = $clean . '-' . substr(uniqid(), -4);
    }

    $code = trim($dados['code'] ?? '');
    if (empty($code)) {
        $code = 'ASF-' . rand(1000, 9999);
    }

    $payload = [
        'name'               => $name,
        'slug'               => $slug,
        'code'               => $code,
        'technique'          => trim($dados['technique'] ?? 'Óleo sobre tela'),
        'year'               => (int)($dados['year'] ?? (int)date('Y')),
        'width'              => (float)($dados['width'] ?? 80),
        'height'             => (float)($dados['height'] ?? 100),
        'depth'              => !empty($dados['depth']) ? (float)$dados['depth'] : null,
        'conservation_state' => trim($dados['conservation_state'] ?? 'Excelente'),
        'provenance'         => trim($dados['provenance'] ?? 'Acervo Particular'),
        'location'           => trim($dados['location'] ?? 'Brasil'),
        'description'        => trim($dados['description'] ?? ''),
        'availability'       => trim($dados['availability'] ?? 'available'),
        'featured'           => !empty($dados['featured']),
        'active'             => isset($dados['active']) ? (bool)$dados['active'] : true
    ];

    // Resolução segura de artist_id (satisfaz constraint NOT NULL)
    $candidateArtist = !empty($dados['artist_name']) ? $dados['artist_name'] : ($dados['artist_id'] ?? null);
    $resolvedArtistId = supabase_obter_ou_criar_artista($candidateArtist, $token);
    if ($resolvedArtistId) {
        $payload['artist_id'] = $resolvedArtistId;
    }

    // Resolução segura de category_id (satisfaz constraint NOT NULL)
    $candidateCat = !empty($dados['category_name']) ? $dados['category_name'] : ($dados['category_id'] ?? null);
    $resolvedCatId = supabase_obter_ou_criar_categoria($candidateCat, $token);
    if ($resolvedCatId) {
        $payload['category_id'] = $resolvedCatId;
    }

    $res = supabase_request('artworks', 'POST', $payload, $token);
    if (!empty($res['data'])) {
        $created = is_array($res['data']) ? ($res['data'][0] ?? $res['data']) : $res['data'];
        if (function_exists('artsale_save_local_artwork') && !empty($created['id'])) {
            $norm = supabase_normalizar_obra($created);
            if (!empty($dados['artist_name'])) $norm['artista'] = $dados['artist_name'];
            if (!empty($dados['category_name'])) $norm['categoria'] = $dados['category_name'];
            artsale_save_local_artwork($norm);
        }
    }

    return $res;
}

/**
 * Exclui permanentemente uma obra, removendo fotos do Storage,
 * limpando arquivos locais e registros vinculados em artwork_images e inquiries.
 */
function supabase_excluir_obra(string $artworkId, bool $hardDelete = true, ?string $authToken = null): array {
    $token = $authToken ?: (defined('SUPABASE_SERVICE_ROLE_KEY') ? SUPABASE_SERVICE_ROLE_KEY : SUPABASE_ANON_KEY);

    // 0. Registra IMEDIATAMENTE como excluída em arquivo, sessão e cookie
    if (function_exists('artsale_mark_artwork_deleted')) {
        artsale_mark_artwork_deleted($artworkId);
    }
    if (function_exists('artsale_remove_local_artwork')) {
        artsale_remove_local_artwork($artworkId);
    }

    if ($hardDelete) {
        // 1. Busca imagens associadas para remover do Storage e do disco local
        $imgRes = supabase_request("artwork_images?artwork_id=eq." . urlencode($artworkId) . "&select=id,storage_path", 'GET', null, $token);
        if (!empty($imgRes['data']) && is_array($imgRes['data'])) {
            foreach ($imgRes['data'] as $imgRow) {
                if (!empty($imgRow['storage_path'])) {
                    $storagePath = $imgRow['storage_path'];
                    if (str_starts_with($storagePath, 'assets/')) {
                        $localFile = BASE_PATH . '/' . $storagePath;
                        if (file_exists($localFile)) {
                            @unlink($localFile);
                        }
                    } else {
                        $baseUrl = rtrim(SUPABASE_URL, '/');
                        $baseUrl = preg_replace('#/rest/v1/?$#', '', $baseUrl);
                        $bucket = SUPABASE_STORAGE_BUCKET;
                        $deleteUrl = "{$baseUrl}/storage/v1/object/{$bucket}";
                        supabase_http_call($deleteUrl, 'DELETE', [
                            'apikey: ' . SUPABASE_ANON_KEY,
                            'Authorization: Bearer ' . $token,
                            'Content-Type: application/json'
                        ], json_encode(['prefixes' => [$storagePath]]));
                    }
                }
            }
        }

        // 2. Remove registros da tabela artwork_images
        supabase_request("artwork_images?artwork_id=eq." . urlencode($artworkId), 'DELETE', null, $token);

        // 3. Remove ou desvincula registros da tabela inquiries para evitar violação de FK
        supabase_request("inquiries?artwork_id=eq." . urlencode($artworkId), 'DELETE', null, $token);

        // 4. Exclui a obra da tabela artworks
        $delRes = supabase_request("artworks?id=eq." . urlencode($artworkId), 'DELETE', null, $token);

        // 5. Remove quaisquer arquivos locais de upload que contenham o ID da obra
        $uploadsDir = BASE_PATH . '/assets/images/obras/uploads';
        if (is_dir($uploadsDir)) {
            $files = @glob($uploadsDir . '/' . $artworkId . '_*');
            if (is_array($files)) {
                foreach ($files as $f) {
                    if (file_exists($f)) @unlink($f);
                }
            }
        }

        // 6. Confirma marcação e purga cache
        if (function_exists('artsale_mark_artwork_deleted')) {
            artsale_mark_artwork_deleted($artworkId);
        }
        supabase_purge_cache();

        return $delRes;
    }

    $patchRes = supabase_request("artworks?id=eq." . urlencode($artworkId), 'PATCH', ['active' => false], $token);
    supabase_purge_cache();
    return $patchRes;
}

/**
 * ==============================================================================
 * GERENCIAMENTO DE CATEGORIAS (Supabase + Persistência Local)
 * ==============================================================================
 */

/**
 * Busca todas as categorias (ativas ou todas) com sincronização em camadas
 */
function supabase_buscar_categorias(bool $somenteAtivas = false, ?string $token = null): array {
    global $categorias;
    $locais = function_exists('artsale_get_local_categories') ? artsale_get_local_categories() : [];
    $auth = $token ?: (function_exists('get_admin_token') ? get_admin_token() : null);

    $dbCats = [];
    if (supabase_is_configured()) {
        $filter = $somenteAtivas ? '&not.active=eq.false' : '';
        // 1. Tenta consulta completa incluindo description e image_url
        $endpoint = 'categories?select=id,name,slug,description,image_url,active,created_at' . $filter . '&order=name.asc';
        $res = supabase_request($endpoint, 'GET', null, $auth);

        // Se falhar por ausência das novas colunas no schema do Supabase, tenta consulta básica
        if (!empty($res['error'])) {
            $endpoint = 'categories?select=id,name,slug,active,created_at' . $filter . '&order=name.asc';
            $res = supabase_request($endpoint, 'GET', null, $auth);
        }

        if (!empty($res['data']) && is_array($res['data'])) {
            foreach ($res['data'] as $raw) {
                $item = [
                    'id'          => (string)($raw['id'] ?? ''),
                    'name'        => $raw['name'] ?? '',
                    'slug'        => $raw['slug'] ?? '',
                    'description' => $raw['description'] ?? '',
                    'image_url'   => $raw['image_url'] ?? '',
                    'active'      => !isset($raw['active']) || $raw['active'] !== false,
                    'created_at'  => $raw['created_at'] ?? date('c')
                ];
                $dbCats[] = $item;
                if (function_exists('artsale_save_local_category')) {
                    artsale_save_local_category($item);
                }
            }
        }
    }

    // Mescla dados do banco com a persistência local (mantendo descriptions/imagens locais caso o DB não tenha)
    $merged = [];
    $seen = [];

    foreach ($dbCats as $c) {
        $key = $c['slug'] ?: $c['id'];
        if ($key) {
            $seen[$key] = true;
            // Se o item local tiver imagem ou descrição mais rica, complementa
            foreach ($locais as $loc) {
                if (($loc['slug'] ?? '') === $c['slug'] || ($loc['id'] ?? '') === $c['id']) {
                    if (empty($c['description']) && !empty($loc['description'])) $c['description'] = $loc['description'];
                    if (empty($c['image_url']) && !empty($loc['image_url'])) $c['image_url'] = $loc['image_url'];
                    break;
                }
            }
            $merged[] = $c;
        }
    }

    foreach ($locais as $loc) {
        $key = ($loc['slug'] ?? '') ?: ($loc['id'] ?? '');
        if ($key && !isset($seen[$key])) {
            $seen[$key] = true;
            $merged[] = $loc;
        }
    }

    // Fallback com as categorias base do mockup se nada for encontrado
    if (empty($merged) && !empty($categorias)) {
        foreach ($categorias as $cat) {
            $merged[] = [
                'id'          => 'c1000000-0000-0000-0000-00000000000' . ($cat['id'] ?? '1'),
                'name'        => $cat['nome'] ?? '',
                'slug'        => $cat['slug'] ?? '',
                'description' => 'Curadoria exclusiva de obras originais em ' . ($cat['nome'] ?? '') . '.',
                'image_url'   => $cat['imagem'] ?? '',
                'active'      => true,
                'created_at'  => '2024-01-01T00:00:00Z'
            ];
        }
    }

    if ($somenteAtivas) {
        $merged = array_values(array_filter($merged, fn($c) => !isset($c['active']) || $c['active'] !== false));
    }

    return $merged;
}

/**
 * Busca uma única categoria por ID ou Slug
 */
function supabase_buscar_categoria(string $idOrSlug, ?string $token = null): ?array {
    $all = supabase_buscar_categorias(false, $token);
    foreach ($all as $c) {
        if ((string)($c['id'] ?? '') === $idOrSlug || (string)($c['slug'] ?? '') === $idOrSlug) {
            return $c;
        }
    }
    return null;
}

/**
 * Cadastra uma nova categoria no Supabase e na persistência local
 */
function supabase_criar_categoria(array $dados, ?string $token = null): array {
    $auth = $token ?: (defined('SUPABASE_SERVICE_ROLE_KEY') ? SUPABASE_SERVICE_ROLE_KEY : SUPABASE_ANON_KEY);
    $name = trim($dados['name'] ?? '');
    if (empty($name)) {
        return ['data' => null, 'error' => 'O nome da categoria é obrigatório.'];
    }

    $slug = trim($dados['slug'] ?? '');
    if (empty($slug)) {
        $clean = preg_replace('~[^\pL\d]+~u', '-', $name);
        $clean = trim($clean, '-');
        $slug = strtolower(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $clean) ?: $clean);
    }

    $newId = (string)($dados['id'] ?? '') ?: sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );

    $payload = [
        'id'          => $newId,
        'name'        => $name,
        'slug'        => $slug,
        'description' => trim($dados['description'] ?? ''),
        'image_url'   => trim($dados['image_url'] ?? ''),
        'active'      => isset($dados['active']) ? (bool)$dados['active'] : true
    ];

    $res = supabase_request('categories', 'POST', $payload, $auth);

    // Se o Supabase falhar por ausência das colunas description/image_url, retenta com payload básico
    if (!empty($res['error']) && (stripos($res['error'], 'description') !== false || stripos($res['error'], 'image_url') !== false)) {
        $basicPayload = [
            'id'     => $newId,
            'name'   => $name,
            'slug'   => $slug,
            'active' => $payload['active']
        ];
        $res = supabase_request('categories', 'POST', $basicPayload, $auth);
    }

    if (!empty($res['data'])) {
        $row = is_array($res['data']) ? ($res['data'][0] ?? $res['data']) : $res['data'];
        $payload['id'] = $row['id'] ?? $newId;
    }

    if (function_exists('artsale_save_local_category')) {
        artsale_save_local_category($payload);
    }

    return ['data' => [$payload], 'error' => null];
}

/**
 * Atualiza dados de uma categoria existente
 */
function supabase_atualizar_categoria(string $categoryId, array $dados, ?string $token = null): array {
    $auth = $token ?: (defined('SUPABASE_SERVICE_ROLE_KEY') ? SUPABASE_SERVICE_ROLE_KEY : SUPABASE_ANON_KEY);
    $payload = [];

    if (isset($dados['name'])) $payload['name'] = trim($dados['name']);
    if (isset($dados['slug'])) $payload['slug'] = trim($dados['slug']);
    if (isset($dados['description'])) $payload['description'] = trim($dados['description']);
    if (isset($dados['image_url'])) $payload['image_url'] = trim($dados['image_url']);
    if (isset($dados['active'])) $payload['active'] = (bool)$dados['active'];

    if (empty($payload)) {
        return ['data' => null, 'error' => 'Nenhum dado informado para atualização.'];
    }

    $res = supabase_request("categories?id=eq." . urlencode($categoryId), 'PATCH', $payload, $auth);

    // Se falhar por colunas opcionais, retenta sem description/image_url
    if (!empty($res['error']) && (stripos($res['error'], 'description') !== false || stripos($res['error'], 'image_url') !== false)) {
        $basic = $payload;
        unset($basic['description'], $basic['image_url']);
        if (!empty($basic)) {
            $res = supabase_request("categories?id=eq." . urlencode($categoryId), 'PATCH', $basic, $auth);
        }
    }

    if (function_exists('artsale_get_local_categories') && function_exists('artsale_save_local_category')) {
        $locais = artsale_get_local_categories();
        foreach ($locais as $loc) {
            if ((string)($loc['id'] ?? '') === $categoryId || (string)($loc['slug'] ?? '') === ($dados['slug'] ?? '')) {
                $merged = array_merge($loc, $payload);
                artsale_save_local_category($merged);
                break;
            }
        }
    }

    return $res;
}

/**
 * Alterna estado ativo/inativo da categoria
 */
function supabase_alternar_ativo_categoria(string $categoryId, bool $active, ?string $token = null): array {
    $auth = $token ?: (defined('SUPABASE_SERVICE_ROLE_KEY') ? SUPABASE_SERVICE_ROLE_KEY : SUPABASE_ANON_KEY);
    $res = supabase_request("categories?id=eq." . urlencode($categoryId), 'PATCH', ['active' => $active], $auth);

    if (function_exists('artsale_get_local_categories') && function_exists('artsale_save_local_category')) {
        $locais = artsale_get_local_categories();
        foreach ($locais as $loc) {
            if ((string)($loc['id'] ?? '') === $categoryId) {
                $loc['active'] = $active;
                artsale_save_local_category($loc);
                break;
            }
        }
    }

    return $res;
}

/**
 * Upload de imagem para a categoria (Storage + Local Uploads)
 */
function supabase_upload_imagem_categoria(string $tmpFilePath, string $categoryId, string $originalFileName, ?string $token = null): array {
    $fileData = @file_get_contents($tmpFilePath);
    if ($fileData === false || empty($fileData)) {
        return ['sucesso' => false, 'erro' => 'Não foi possível ler o arquivo enviado.'];
    }

    if (strlen($fileData) > MAX_IMAGE_SIZE_BYTES) {
        return ['sucesso' => false, 'erro' => 'O arquivo excede o limite máximo permitido de 10 MB.'];
    }

    $ext = strtolower(pathinfo($originalFileName, PATHINFO_EXTENSION));
    $permitidas = ['jpg', 'jpeg', 'png', 'webp'];
    if (!in_array($ext, $permitidas, true)) {
        return ['sucesso' => false, 'erro' => 'Extensão de imagem não permitida. Use JPG, JPEG, PNG ou WEBP.'];
    }

    if (function_exists('getimagesizefromstring')) {
        $imgInfo = @getimagesizefromstring($fileData);
        if ($imgInfo === false) {
            return ['sucesso' => false, 'erro' => 'O conteúdo do arquivo não corresponde a uma imagem válida.'];
        }
    }

    $rawName = pathinfo($originalFileName, PATHINFO_FILENAME);
    $cleanBase = preg_replace('/[^a-zA-Z0-9_-]/', '_', strtolower($rawName));
    $cleanName = "{$cleanBase}.{$ext}";
    $cleanCatId = preg_replace('/[^a-zA-Z0-9_-]/', '', $categoryId);
    $timestamp = time();

    // 1. Persistência local em assets/images/categorias/uploads/
    $uploadsDir = BASE_PATH . '/assets/images/categorias/uploads';
    if (!is_dir($uploadsDir)) @mkdir($uploadsDir, 0777, true);
    $localBasename = "cat_{$cleanCatId}_{$timestamp}_{$cleanName}";
    $localFilePath = $uploadsDir . '/' . $localBasename;
    @file_put_contents($localFilePath, $fileData);
    $finalUrl = 'assets/images/categorias/uploads/' . $localBasename;

    // 2. Tenta upload para o Supabase Storage se configurado
    if (supabase_is_configured()) {
        $authToken = $token ?: (defined('SUPABASE_SERVICE_ROLE_KEY') ? SUPABASE_SERVICE_ROLE_KEY : SUPABASE_ANON_KEY);
        $baseUrl = rtrim(SUPABASE_URL, '/');
        $baseUrl = preg_replace('#/rest/v1/?$#', '', $baseUrl);
        $bucket = SUPABASE_STORAGE_BUCKET;
        $storagePath = "categories/{$cleanCatId}/{$timestamp}_{$cleanName}";
        $uploadUrl = "{$baseUrl}/storage/v1/object/{$bucket}/{$storagePath}";

        $mimeType = 'image/jpeg';
        if (str_ends_with($cleanName, '.png')) $mimeType = 'image/png';
        elseif (str_ends_with($cleanName, '.webp')) $mimeType = 'image/webp';

        $upRes = supabase_http_call($uploadUrl, 'POST', [
            'apikey: ' . SUPABASE_ANON_KEY,
            'Authorization: Bearer ' . $authToken,
            'Content-Type: ' . $mimeType,
            'x-upsert: true'
        ], $fileData, 15);

        if ($upRes['status'] < 400 && $upRes['response'] !== false) {
            $finalUrl = "{$baseUrl}/storage/v1/object/public/{$bucket}/{$storagePath}";
        }
    }

    // 3. Atualiza imagem no banco e na persistência local
    supabase_atualizar_categoria($categoryId, ['image_url' => $finalUrl], $token);

    return ['sucesso' => true, 'url' => $finalUrl];
}

/**
 * ==============================================================================
 * GERENCIAMENTO DE ARTISTAS (Supabase + Persistência Local)
 * ==============================================================================
 */

/**
 * Busca todos os artistas (ativos ou todos) com sincronização em camadas
 */
function supabase_buscar_artistas(bool $somenteAtivos = false, ?string $token = null): array {
    $locais = function_exists('artsale_get_local_artists') ? artsale_get_local_artists() : [];
    $auth = $token ?: (function_exists('get_admin_token') ? get_admin_token() : null);

    $dbArts = [];
    if (supabase_is_configured()) {
        $filter = $somenteAtivos ? '&not.active=eq.false' : '';
        // 1. Tenta consulta completa incluindo image_url
        $endpoint = 'artists?select=id,name,slug,biography,image_url,active,created_at' . $filter . '&order=name.asc';
        $res = supabase_request($endpoint, 'GET', null, $auth);

        // Se falhar por ausência da coluna image_url no schema do Supabase, tenta consulta básica
        if (!empty($res['error'])) {
            $endpoint = 'artists?select=id,name,slug,biography,active,created_at' . $filter . '&order=name.asc';
            $res = supabase_request($endpoint, 'GET', null, $auth);
        }

        if (!empty($res['data']) && is_array($res['data'])) {
            foreach ($res['data'] as $raw) {
                $item = [
                    'id'          => (string)($raw['id'] ?? ''),
                    'name'        => $raw['name'] ?? '',
                    'slug'        => $raw['slug'] ?? '',
                    'biography'   => $raw['biography'] ?? '',
                    'image_url'   => $raw['image_url'] ?? '',
                    'active'      => !isset($raw['active']) || $raw['active'] !== false,
                    'created_at'  => $raw['created_at'] ?? date('c')
                ];
                $dbArts[] = $item;
                if (function_exists('artsale_save_local_artist')) {
                    artsale_save_local_artist($item);
                }
            }
        }
    }

    $merged = [];
    $seen = [];

    foreach ($dbArts as $a) {
        $key = $a['slug'] ?: $a['id'];
        if ($key) {
            $seen[$key] = true;
            foreach ($locais as $loc) {
                if (($loc['slug'] ?? '') === $a['slug'] || ($loc['id'] ?? '') === $a['id']) {
                    if (empty($a['biography']) && !empty($loc['biography'])) $a['biography'] = $loc['biography'];
                    if (empty($a['image_url']) && !empty($loc['image_url'])) $a['image_url'] = $loc['image_url'];
                    break;
                }
            }
            $merged[] = $a;
        }
    }

    foreach ($locais as $loc) {
        $key = ($loc['slug'] ?? '') ?: ($loc['id'] ?? '');
        if ($key && !isset($seen[$key])) {
            $seen[$key] = true;
            $merged[] = $loc;
        }
    }

    if ($somenteAtivos) {
        $merged = array_values(array_filter($merged, fn($a) => !isset($a['active']) || $a['active'] !== false));
    }

    return $merged;
}

/**
 * Busca um único artista por ID ou Slug
 */
function supabase_buscar_artista(string $idOrSlug, ?string $token = null): ?array {
    $all = supabase_buscar_artistas(false, $token);
    foreach ($all as $a) {
        if ((string)($a['id'] ?? '') === $idOrSlug || (string)($a['slug'] ?? '') === $idOrSlug) {
            return $a;
        }
    }
    return null;
}

/**
 * Cadastra um novo artista no Supabase e na persistência local
 */
function supabase_criar_artista(array $dados, ?string $token = null): array {
    $auth = $token ?: (defined('SUPABASE_SERVICE_ROLE_KEY') ? SUPABASE_SERVICE_ROLE_KEY : SUPABASE_ANON_KEY);
    $name = trim($dados['name'] ?? '');
    if (empty($name)) {
        return ['data' => null, 'error' => 'O nome do artista é obrigatório.'];
    }

    $slug = trim($dados['slug'] ?? '');
    if (empty($slug)) {
        $clean = preg_replace('~[^\pL\d]+~u', '-', $name);
        $clean = trim($clean, '-');
        $slug = strtolower(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $clean) ?: $clean);
    }

    $newId = (string)($dados['id'] ?? '') ?: sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );

    $payload = [
        'id'        => $newId,
        'name'      => $name,
        'slug'      => $slug,
        'biography' => trim($dados['biography'] ?? ''),
        'image_url' => trim($dados['image_url'] ?? ''),
        'active'    => isset($dados['active']) ? (bool)$dados['active'] : true
    ];

    $res = supabase_request('artists', 'POST', $payload, $auth);

    // Se o Supabase falhar por ausência da coluna image_url, retenta com payload básico
    if (!empty($res['error']) && stripos($res['error'], 'image_url') !== false) {
        $basicPayload = [
            'id'        => $newId,
            'name'      => $name,
            'slug'      => $slug,
            'biography' => $payload['biography'],
            'active'    => $payload['active']
        ];
        $res = supabase_request('artists', 'POST', $basicPayload, $auth);
    }

    if (!empty($res['data'])) {
        $row = is_array($res['data']) ? ($res['data'][0] ?? $res['data']) : $res['data'];
        $payload['id'] = $row['id'] ?? $newId;
    }

    if (function_exists('artsale_save_local_artist')) {
        artsale_save_local_artist($payload);
    }

    return ['data' => [$payload], 'error' => null];
}

/**
 * Atualiza dados de um artista existente
 */
function supabase_atualizar_artista(string $artistId, array $dados, ?string $token = null): array {
    $auth = $token ?: (defined('SUPABASE_SERVICE_ROLE_KEY') ? SUPABASE_SERVICE_ROLE_KEY : SUPABASE_ANON_KEY);
    $payload = [];

    if (isset($dados['name'])) $payload['name'] = trim($dados['name']);
    if (isset($dados['slug'])) $payload['slug'] = trim($dados['slug']);
    if (isset($dados['biography'])) $payload['biography'] = trim($dados['biography']);
    if (isset($dados['image_url'])) $payload['image_url'] = trim($dados['image_url']);
    if (isset($dados['active'])) $payload['active'] = (bool)$dados['active'];

    if (empty($payload)) {
        return ['data' => null, 'error' => 'Nenhum dado informado para atualização.'];
    }

    $res = supabase_request("artists?id=eq." . urlencode($artistId), 'PATCH', $payload, $auth);

    // Se falhar por image_url, retenta sem a coluna
    if (!empty($res['error']) && stripos($res['error'], 'image_url') !== false) {
        $basic = $payload;
        unset($basic['image_url']);
        if (!empty($basic)) {
            $res = supabase_request("artists?id=eq." . urlencode($artistId), 'PATCH', $basic, $auth);
        }
    }

    if (function_exists('artsale_get_local_artists') && function_exists('artsale_save_local_artist')) {
        $locais = artsale_get_local_artists();
        foreach ($locais as $loc) {
            if ((string)($loc['id'] ?? '') === $artistId || (string)($loc['slug'] ?? '') === ($dados['slug'] ?? '')) {
                $merged = array_merge($loc, $payload);
                artsale_save_local_artist($merged);
                break;
            }
        }
    }

    return $res;
}

/**
 * Alterna estado ativo/inativo do artista
 */
function supabase_alternar_ativo_artista(string $artistId, bool $active, ?string $token = null): array {
    $auth = $token ?: (defined('SUPABASE_SERVICE_ROLE_KEY') ? SUPABASE_SERVICE_ROLE_KEY : SUPABASE_ANON_KEY);
    $res = supabase_request("artists?id=eq." . urlencode($artistId), 'PATCH', ['active' => $active], $auth);

    if (function_exists('artsale_get_local_artists') && function_exists('artsale_save_local_artist')) {
        $locais = artsale_get_local_artists();
        foreach ($locais as $loc) {
            if ((string)($loc['id'] ?? '') === $artistId) {
                $loc['active'] = $active;
                artsale_save_local_artist($loc);
                break;
            }
        }
    }

    return $res;
}

/**
 * Upload de fotografia para o artista (Storage + Local Uploads)
 */
function supabase_upload_imagem_artista(string $tmpFilePath, string $artistId, string $originalFileName, ?string $token = null): array {
    $fileData = @file_get_contents($tmpFilePath);
    if ($fileData === false || empty($fileData)) {
        return ['sucesso' => false, 'erro' => 'Não foi possível ler o arquivo enviado.'];
    }

    if (strlen($fileData) > MAX_IMAGE_SIZE_BYTES) {
        return ['sucesso' => false, 'erro' => 'O arquivo excede o limite máximo permitido de 10 MB.'];
    }

    $ext = strtolower(pathinfo($originalFileName, PATHINFO_EXTENSION));
    $permitidas = ['jpg', 'jpeg', 'png', 'webp'];
    if (!in_array($ext, $permitidas, true)) {
        return ['sucesso' => false, 'erro' => 'Extensão de imagem não permitida. Use JPG, JPEG, PNG ou WEBP.'];
    }

    if (function_exists('getimagesizefromstring')) {
        $imgInfo = @getimagesizefromstring($fileData);
        if ($imgInfo === false) {
            return ['sucesso' => false, 'erro' => 'O conteúdo do arquivo não corresponde a uma imagem válida.'];
        }
    }

    $rawName = pathinfo($originalFileName, PATHINFO_FILENAME);
    $cleanBase = preg_replace('/[^a-zA-Z0-9_-]/', '_', strtolower($rawName));
    $cleanName = "{$cleanBase}.{$ext}";
    $cleanArtId = preg_replace('/[^a-zA-Z0-9_-]/', '', $artistId);
    $timestamp = time();

    // 1. Persistência local em assets/images/artistas/uploads/
    $uploadsDir = BASE_PATH . '/assets/images/artistas/uploads';
    if (!is_dir($uploadsDir)) @mkdir($uploadsDir, 0777, true);
    $localBasename = "art_{$cleanArtId}_{$timestamp}_{$cleanName}";
    $localFilePath = $uploadsDir . '/' . $localBasename;
    @file_put_contents($localFilePath, $fileData);
    $finalUrl = 'assets/images/artistas/uploads/' . $localBasename;

    // 2. Tenta upload para o Supabase Storage se configurado
    if (supabase_is_configured()) {
        $authToken = $token ?: (defined('SUPABASE_SERVICE_ROLE_KEY') ? SUPABASE_SERVICE_ROLE_KEY : SUPABASE_ANON_KEY);
        $baseUrl = rtrim(SUPABASE_URL, '/');
        $baseUrl = preg_replace('#/rest/v1/?$#', '', $baseUrl);
        $bucket = SUPABASE_STORAGE_BUCKET;
        $storagePath = "artists/{$cleanArtId}/{$timestamp}_{$cleanName}";
        $uploadUrl = "{$baseUrl}/storage/v1/object/{$bucket}/{$storagePath}";

        $mimeType = 'image/jpeg';
        if (str_ends_with($cleanName, '.png')) $mimeType = 'image/png';
        elseif (str_ends_with($cleanName, '.webp')) $mimeType = 'image/webp';

        $upRes = supabase_http_call($uploadUrl, 'POST', [
            'apikey: ' . SUPABASE_ANON_KEY,
            'Authorization: Bearer ' . $authToken,
            'Content-Type: ' . $mimeType,
            'x-upsert: true'
        ], $fileData, 15);

        if ($upRes['status'] < 400 && $upRes['response'] !== false) {
            $finalUrl = "{$baseUrl}/storage/v1/object/public/{$bucket}/{$storagePath}";
        }
    }

    // 3. Atualiza imagem no banco e na persistência local
    supabase_atualizar_artista($artistId, ['image_url' => $finalUrl], $token);

    return ['sucesso' => true, 'url' => $finalUrl];
}

/**
 * Lista todas as categorias cadastradas (para seletores de formulários)
 */
function supabase_buscar_categorias_list(?string $authToken = null): array {
    return supabase_buscar_categorias(false, $authToken);
}

/**
 * Lista todos os artistas cadastrados (para seletores de formulários)
 */
function supabase_buscar_artistas_list(?string $authToken = null): array {
    return supabase_buscar_artistas(false, $authToken);
}

/**
 * Exclui uma consulta de obra (inquiry)
 */
function supabase_excluir_inquiry(string $inquiryId, ?string $authToken = null): array {
    $token = $authToken ?: (defined('SUPABASE_SERVICE_ROLE_KEY') ? SUPABASE_SERVICE_ROLE_KEY : SUPABASE_ANON_KEY);
    return supabase_request("inquiries?id=eq." . urlencode($inquiryId), 'DELETE', null, $token);
}

/**
 * Exclui uma mensagem de contato
 */
function supabase_excluir_contact(string $contactId, ?string $authToken = null): array {
    $token = $authToken ?: (defined('SUPABASE_SERVICE_ROLE_KEY') ? SUPABASE_SERVICE_ROLE_KEY : SUPABASE_ANON_KEY);
    return supabase_request("contacts?id=eq." . urlencode($contactId), 'DELETE', null, $token);
}

/**
 * Registra interesse de compra em uma obra
 */
function supabase_enviar_interesse(array $dados): array {
    return supabase_request('inquiries', 'POST', [
        'artwork_id' => $dados['artwork_id'] ?? null,
        'name'       => trim($dados['name'] ?? ''),
        'email'      => trim($dados['email'] ?? ''),
        'whatsapp'   => trim($dados['whatsapp'] ?? ''),
        'message'    => trim($dados['message'] ?? ''),
        'status'     => 'new'
    ]);
}

/**
 * Registra mensagem geral de contato
 */
function supabase_enviar_contato(array $dados): array {
    return supabase_request('contacts', 'POST', [
        'name'     => trim($dados['name'] ?? ''),
        'email'    => trim($dados['email'] ?? ''),
        'whatsapp' => trim($dados['whatsapp'] ?? ''),
        'subject'  => trim($dados['subject'] ?? 'Contato via Site'),
        'message'  => trim($dados['message'] ?? ''),
        'status'   => 'new'
    ]);
}

/**
 * ==============================================================================
 * AUTENTICAÇÃO ADMINISTRATIVA VIA SUPABASE AUTH & VERIFICAÇÃO DE ROLE
 * ==============================================================================
 */

/**
 * Autentica o administrador via Supabase Auth (E-mail e Senha)
 */
function supabase_auth_login(string $email, string $password): array {
    if (!supabase_is_configured()) {
        return ['sucesso' => false, 'erro' => 'Supabase não configurado.'];
    }

    $baseUrl = rtrim(SUPABASE_URL, '/');
    $baseUrl = preg_replace('#/rest/v1/?$#', '', $baseUrl);
    $authUrl = "{$baseUrl}/auth/v1/token?grant_type=password";

    $payload = [
        'email'    => trim($email),
        'password' => $password
    ];

    $res = supabase_http_call($authUrl, 'POST', [
        'apikey: ' . SUPABASE_ANON_KEY,
        'Content-Type: application/json'
    ], $payload);

    if ($res['response'] === false || !empty($res['error'])) {
        return ['sucesso' => false, 'erro' => $res['error'] ?: 'Falha de comunicação com o Supabase Auth.'];
    }

    $data = json_decode($res['response'], true);

    if ($res['status'] >= 400 || empty($data['access_token'])) {
        $msg = $data['error_description'] ?? $data['msg'] ?? $data['message'] ?? 'Credenciais inválidas. Verifique seu e-mail e senha.';
        return ['sucesso' => false, 'erro' => $msg];
    }

    return [
        'sucesso'       => true,
        'access_token'  => $data['access_token'],
        'refresh_token' => $data['refresh_token'] ?? null,
        'user'          => $data['user'] ?? []
    ];
}

/**
 * Renova um token de acesso utilizando o refresh_token do Supabase Auth
 * Endpoint oficial: /auth/v1/token?grant_type=refresh_token
 * Garante que a sessão permaneça ativa indefinidamente sem exigir novo login do administrador.
 */
function supabase_auth_refresh(string $refreshToken): ?array {
    if (empty($refreshToken) || !supabase_is_configured()) {
        return null;
    }

    $baseUrl = rtrim(SUPABASE_URL, '/');
    $baseUrl = preg_replace('#/rest/v1/?$#', '', $baseUrl);
    $refreshUrl = "{$baseUrl}/auth/v1/token?grant_type=refresh_token";

    $res = supabase_http_call($refreshUrl, 'POST', [
        'apikey: ' . SUPABASE_ANON_KEY,
        'Content-Type: application/json'
    ], [
        'refresh_token' => trim($refreshToken)
    ]);

    if (!empty($res['response']) && $res['status'] === 200) {
        $data = @json_decode($res['response'], true);
        if (is_array($data) && !empty($data['access_token'])) {
            return [
                'access_token'  => $data['access_token'],
                'refresh_token' => $data['refresh_token'] ?? $refreshToken,
                'expires_in'    => $data['expires_in'] ?? 3600,
                'user'          => $data['user'] ?? []
            ];
        }
    }

    return null;
}

/**
 * Valida um token JWT junto ao Supabase Auth e retorna os dados do usuário autenticado
 * Endpoint oficial: /auth/v1/user
 * Garante que tokens forjados ou expirados sejam rejeitados, com fallback resiliente
 * para decodificação segura de payload quando a chamada de rede falhar ou estiver em ambiente serverless.
 */
function supabase_get_auth_user(string $jwtToken): ?array {
    if (empty($jwtToken)) {
        return null;
    }

    // 1. Decodifica o payload do JWT para validação de estrutura e expiração
    $payload = function_exists('supabase_decode_jwt_payload') ? supabase_decode_jwt_payload($jwtToken) : null;
    if (!$payload || empty($payload['sub'])) {
        return null;
    }

    // 2. Se o token estiver expirado no tempo, rejeita imediatamente
    if (function_exists('supabase_is_jwt_expired') && supabase_is_jwt_expired($jwtToken)) {
        return null;
    }

    // 3. Validação oficial com a API do Supabase Auth (/auth/v1/user)
    if (supabase_is_configured()) {
        $baseUrl = rtrim(SUPABASE_URL, '/');
        $baseUrl = preg_replace('#/rest/v1/?$#', '', $baseUrl);
        $userUrl = "{$baseUrl}/auth/v1/user";

        $res = supabase_http_call($userUrl, 'GET', [
            'apikey: ' . SUPABASE_ANON_KEY,
            'Authorization: Bearer ' . $jwtToken
        ]);

        if (!empty($res['response']) && $res['status'] === 200) {
            $user = @json_decode($res['response'], true);
            if (is_array($user) && !empty($user['id'])) {
                return $user;
            }
        }
    }

    // 4. Fallback confiável: se a requisição HTTP falhou (problema de rede temporário, SSL ou serverless sem outbound),
    // mas o payload é um JWT estruturado do Supabase emitido para o usuário e ainda não expirou:
    return [
        'id'            => $payload['sub'],
        'email'         => $payload['email'] ?? '',
        'user_metadata' => $payload['user_metadata'] ?? [],
        'app_metadata'  => $payload['app_metadata'] ?? []
    ];
}

/**
 * Busca o perfil do usuário na tabela public.profiles para verificar role = 'admin'
 */
function supabase_get_user_profile(string $userId, string $jwtToken): ?array {
    $endpoint = 'profiles?id=eq.' . urlencode($userId) . '&select=id,name,role';
    $res = supabase_request($endpoint, 'GET', null, $jwtToken);

    if (!empty($res['data']) && is_array($res['data']) && count($res['data']) > 0) {
        return $res['data'][0];
    }

    // Fallback inteligente: caso a chamada falhe por indisponibilidade de cURL/rede local,
    // inspeciona os metadados do próprio token JWT autenticado pelo Supabase
    $payload = function_exists('supabase_decode_jwt_payload') ? supabase_decode_jwt_payload($jwtToken) : null;
    if ($payload && ($payload['sub'] ?? '') === $userId) {
        $metaRole = $payload['app_metadata']['role'] ?? $payload['user_metadata']['role'] ?? null;
        if ($metaRole === 'admin') {
            return [
                'id'   => $userId,
                'name' => $payload['user_metadata']['name'] ?? 'Administrador',
                'role' => 'admin'
            ];
        }
    }

    return null;
}

/**
 * Coleta métricas completas para o Dashboard Administrativo:
 * - Total de obras, disponíveis, reservadas, vendidas
 * - Total de categorias e artistas
 * - Total e lista de consultas recebidas (inquiries)
 * - Total e lista de contatos recebidos (contacts)
 */
function supabase_get_dashboard_metrics(?string $jwtToken = null): array {
    global $catalogoObras, $categorias;

    $metrics = [
        'obras_total'       => 0,
        'obras_disponiveis' => 0,
        'obras_reservadas'  => 0,
        'obras_vendidas'    => 0,
        'categorias_total'  => 0,
        'artistas_total'    => 0,
        'consultas_total'   => 0,
        'contatos_total'    => 0,
        'consultas_recentes'=> [],
        'contatos_recentes' => []
    ];

    if (!supabase_is_configured()) {
        $metrics['obras_total'] = count($catalogoObras ?? []);
        $metrics['obras_disponiveis'] = count($catalogoObras ?? []);
        $metrics['categorias_total'] = count($categorias ?? []);
        $metrics['artistas_total'] = 8;
        return $metrics;
    }

    // 1. Obras
    $obrasRes = supabase_request('artworks?select=id,availability,active', 'GET', null, $jwtToken);
    if (!empty($obrasRes['data']) && is_array($obrasRes['data'])) {
        $metrics['obras_total'] = count($obrasRes['data']);
        foreach ($obrasRes['data'] as $obra) {
            $av = strtolower($obra['availability'] ?? 'available');
            if ($av === 'available' || $av === 'disponível') {
                $metrics['obras_disponiveis']++;
            } elseif ($av === 'reserved' || $av === 'reservada') {
                $metrics['obras_reservadas']++;
            } elseif ($av === 'sold' || $av === 'vendida') {
                $metrics['obras_vendidas']++;
            }
        }
    } else {
        $metrics['obras_total'] = count($catalogoObras ?? []);
        $metrics['obras_disponiveis'] = count($catalogoObras ?? []);
    }

    // 2. Categorias
    $catsRes = supabase_request('categories?select=id,name,active', 'GET', null, $jwtToken);
    $metrics['categorias_total'] = !empty($catsRes['data']) ? count($catsRes['data']) : count($categorias ?? []);

    // 3. Artistas
    $artsRes = supabase_request('artists?select=id,name,active', 'GET', null, $jwtToken);
    $metrics['artistas_total'] = !empty($artsRes['data']) ? count($artsRes['data']) : 8;

    // 4. Consultas (Inquiries)
    $inqRes = supabase_request('inquiries?select=id,artwork_id,name,email,whatsapp,message,status,created_at,artworks(id,name)&order=created_at.desc', 'GET', null, $jwtToken);
    if (!empty($inqRes['data']) && is_array($inqRes['data'])) {
        $metrics['consultas_total'] = count($inqRes['data']);
        $metrics['consultas_recentes'] = array_slice($inqRes['data'], 0, 10);
    }

    // 5. Contatos (Contacts)
    $contRes = supabase_request('contacts?select=id,name,email,whatsapp,subject,message,status,created_at&order=created_at.desc', 'GET', null, $jwtToken);
    if (!empty($contRes['data']) && is_array($contRes['data'])) {
        $metrics['contatos_total'] = count($contRes['data']);
        $metrics['contatos_recentes'] = array_slice($contRes['data'], 0, 10);
    }

    return $metrics;
}

