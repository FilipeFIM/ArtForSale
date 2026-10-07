<?php
/**
 * ART FOR SALE - Supabase Keepalive / Heartbeat Endpoint
 * 
 * Mantém o projeto Supabase acordado (evitando congelamento/pausa por inatividade de 7 dias)
 * Executa consultas leves reais no banco de dados via REST API.
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-cache, no-store, must-revalidate');

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/supabase.php';

$startTime = microtime(true);

if (!supabase_is_configured()) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Supabase não está configurado neste ambiente.',
        'timestamp' => date('c')
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// 1. Consulta leve na tabela artworks (força atividade real no PostgREST e no PostgreSQL)
$resArtworks = supabase_request('artworks?select=id,name&limit=1', 'GET');

// 2. Consulta leve complementar na tabela categories
$resCategories = supabase_request('categories?select=id,name&limit=1', 'GET');

$elapsedMs = round((microtime(true) - $startTime) * 1000, 2);

$isOk = empty($resArtworks['error']) && is_array($resArtworks['data']);

if (!$isOk) {
    http_response_code(502);
    echo json_encode([
        'status' => 'warning',
        'message' => 'Falha ao consultar o Supabase.',
        'error' => $resArtworks['error'] ?? 'Erro desconhecido',
        'elapsed_ms' => $elapsedMs,
        'timestamp' => date('c')
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

echo json_encode([
    'status' => 'ok',
    'message' => 'Supabase ativo e operando normalmente. Heartbeat registrado com sucesso!',
    'project' => defined('SUPABASE_URL') ? parse_url(SUPABASE_URL, PHP_URL_HOST) : null,
    'artworks_ping' => !empty($resArtworks['data']),
    'categories_ping' => !empty($resCategories['data']),
    'elapsed_ms' => $elapsedMs,
    'timestamp' => date('c')
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
