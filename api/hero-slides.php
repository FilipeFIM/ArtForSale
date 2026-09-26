<?php
/**
 * ART FOR SALE - API de Slides do Hero
 * Retorna os 3 slides configurados pelo Curador no Painel Administrativo.
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-cache, must-revalidate');

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/supabase.php';

$slides = artsale_get_hero_slides(true);
echo json_encode($slides, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
