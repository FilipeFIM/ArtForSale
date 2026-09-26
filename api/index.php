<?php
/**
 * ART FOR SALE - Vercel Serverless Entry Point & Router
 * Roteia requisições do Vercel para as páginas dinâmicas em PHP.
 */

// Define diretório de trabalho raiz do projeto
$baseDir = dirname(__DIR__);
chdir($baseDir);

$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$parsedPath = parse_url($requestUri, PHP_URL_PATH);
$cleanPath = trim($parsedPath, '/');

// Se for raiz ou vazio, carrega index.php principal
if ($cleanPath === '' || $cleanPath === 'index.php') {
    require $baseDir . '/index.php';
    exit;
}

// Se for requisição para admin
if (str_starts_with($cleanPath, 'admin')) {
    $target = $baseDir . '/' . $cleanPath;
    if (is_file($target)) {
        require $target;
        exit;
    }
    if (is_file($target . '.php')) {
        require $target . '.php';
        exit;
    }
    if (is_dir($target) && is_file($target . '/index.php')) {
        require $target . '/index.php';
        exit;
    }
}

// Se for requisição para pages (ex: pages/obras.php, pages/obra.php)
if (str_starts_with($cleanPath, 'pages/')) {
    $target = $baseDir . '/' . $cleanPath;
    if (is_file($target)) {
        require $target;
        exit;
    }
    if (is_file($target . '.php')) {
        require $target . '.php';
        exit;
    }
}

// Se o arquivo existir fisicamente no projeto
$directFile = $baseDir . '/' . $cleanPath;
if (is_file($directFile)) {
    // Se for PHP, executa
    if (str_ends_with($directFile, '.php')) {
        require $directFile;
        exit;
    }
    // Para outros tipos de arquivo estático
    $mime = mime_content_type($directFile) ?: 'application/octet-stream';
    header('Content-Type: ' . $mime);
    readfile($directFile);
    exit;
}

// Fallback padrão: home
require $baseDir . '/index.php';
