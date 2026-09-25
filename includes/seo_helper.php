<?php
/**
 * ART FOR SALE - Assistente de SEO, Metatags Open Graph & Schema.org Structured Data
 * Arquivo: /includes/seo_helper.php
 * Slogan: "Arte que transforma espaços."
 * 
 * Responsabilidades:
 * - Resolução de URLs canônicas e absolutas para crawlers (WhatsApp, Facebook, Twitter, Google)
 * - Geração dinâmica de tags Open Graph e Twitter Cards
 * - Dados estruturados Schema.org (VisualArtwork, ArtGallery, BreadcrumbList)
 * - Estrita obediência às diretrizes: SEM preços inventados, SEM avaliações falsas, SEM estoque fictício
 */

/**
 * Retorna a URL base do site (protocolo + host)
 */
function artsale_get_base_url(): string {
    if (defined('SITE_BASE_URL') && !empty(SITE_BASE_URL)) {
        return rtrim(SITE_BASE_URL, '/');
    }

    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

    $protocol = $isHttps ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost:8000';

    return rtrim($protocol . $host, '/');
}

/**
 * Converte qualquer caminho relativo ou link de storage em uma URL absoluta completa
 */
function artsale_absolute_url(string $path = ''): string {
    if (empty($path)) {
        return artsale_get_base_url();
    }

    // Se já é uma URL absoluta (ex: Supabase Storage https://...)
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }

    // Remove prefixos ../ ou ./ e barras iniciais
    $cleanPath = preg_replace('#^(\.\./|\./)+#', '', $path);
    $cleanPath = ltrim($cleanPath, '/');

    return artsale_get_base_url() . '/' . $cleanPath;
}

/**
 * Monta o Schema.org de uma Obra de Arte (VisualArtwork)
 * Respeita a regra: NÃO inventar preço numérico, estoque ou avaliações.
 */
function artsale_schema_visual_artwork(array $obra, string $canonicalUrl, string $imageUrl): array {
    $titulo = $obra['titulo'] ?? $obra['name'] ?? 'Obra de Arte';
    $artista = $obra['artista'] ?? 'Artista Convidado';
    $descricao = !empty($obra['descricao']) ? strip_tags($obra['descricao']) : "{$titulo}, obra original de {$artista}. Conheça os detalhes curatoriais na Art For Sale.";
    $tecnica = $obra['tecnica'] ?? 'Óleo sobre tela';
    $ano = (string)($obra['ano'] ?? date('Y'));
    $categoria = $obra['categoria'] ?? 'Pintura';

    $schema = [
        '@context'        => 'https://schema.org',
        '@type'           => 'VisualArtwork',
        'name'            => $titulo,
        'creator'         => [
            '@type' => 'Person',
            'name'  => $artista
        ],
        'artMedium'       => $tecnica,
        'artform'         => $categoria,
        'dateCreated'     => $ano,
        'description'     => $descricao,
        'image'           => $imageUrl,
        'url'             => $canonicalUrl,
        'offers'          => [
            '@type'             => 'Offer',
            'priceSpecification'=> [
                '@type' => 'PriceSpecification',
                'name'  => 'Preço sob consulta'
            ],
            'priceCurrency'     => 'BRL',
            'availability'      => 'https://schema.org/InStock',
            'businessFunction'  => 'https://schema.org/Sell',
            'seller'            => [
                '@type' => 'ArtGallery',
                'name'  => 'Art For Sale',
                'url'   => artsale_get_base_url()
            ]
        ]
    ];

    if (!empty($obra['altura']) && !empty($obra['largura'])) {
        $schema['height'] = [
            '@type'    => 'QuantitativeValue',
            'value'    => (float)$obra['altura'],
            'unitCode' => 'CMT'
        ];
        $schema['width'] = [
            '@type'    => 'QuantitativeValue',
            'value'    => (float)$obra['largura'],
            'unitCode' => 'CMT'
        ];
        if (!empty($obra['profundidade'])) {
            $schema['depth'] = [
                '@type'    => 'QuantitativeValue',
                'value'    => (float)$obra['profundidade'],
                'unitCode' => 'CMT'
            ];
        }
    }

    return $schema;
}

/**
 * Monta o Schema.org da Galeria de Arte (ArtGallery)
 */
function artsale_schema_gallery(): array {
    $baseUrl = artsale_get_base_url();
    return [
        '@context'    => 'https://schema.org',
        '@type'       => 'ArtGallery',
        'name'        => 'Art For Sale',
        'slogan'      => 'Arte que transforma espaços.',
        'description' => 'Descubra obras selecionadas pela Art For Sale. Arte que transforma espaços.',
        'url'         => $baseUrl,
        'logo'        => artsale_absolute_url('assets/images/site/logo.svg'),
        'image'       => artsale_absolute_url('assets/images/site/about-art-sell.jpg'),
        'telephone'   => '+55-51-99126-6414',
        'email'       => defined('SITE_CONTACT_EMAIL') ? SITE_CONTACT_EMAIL : 'artforsale1944@gmail.com',
        'address'     => [
            '@type'           => 'PostalAddress',
            'addressCountry'  => 'BR',
            'addressRegion'   => 'RS'
        ]
    ];
}

/**
 * Monta o Schema.org de BreadcrumbList
 */
function artsale_schema_breadcrumb(array $crumbs): array {
    $items = [];
    $pos = 1;
    foreach ($crumbs as $crumb) {
        $items[] = [
            '@type'    => 'ListItem',
            'position' => $pos++,
            'name'     => $crumb['name'],
            'item'     => $crumb['url']
        ];
    }

    return [
        '@context'        => 'https://schema.org',
        '@type'           => 'BreadcrumbList',
        'itemListElement' => $items
    ];
}

/**
 * Renderiza todas as metatags de SEO, Open Graph, Twitter e Schemas JSON-LD
 */
function artsale_render_seo_tags(array $meta): void {
    $title = $meta['title'] ?? 'Art For Sale';
    $description = $meta['description'] ?? 'Descubra obras selecionadas pela Art For Sale. Arte que transforma espaços.';
    $canonical = $meta['canonical'] ?? artsale_get_base_url();
    $ogType = $meta['og_type'] ?? 'website';
    $ogImage = $meta['og_image'] ?? artsale_absolute_url('assets/images/site/about-art-sell.jpg');
    $ogUrl = $meta['og_url'] ?? $canonical;
    $ogTitle = $meta['og_title'] ?? $title;
    $ogDesc = $meta['og_description'] ?? $description;
    $schemas = $meta['schemas'] ?? [];
    ?>
    <!-- Metatags Primárias e Indexação -->
    <title><?= htmlspecialchars($title) ?></title>
    <meta name="title" content="<?= htmlspecialchars($title) ?>">
    <meta name="description" content="<?= htmlspecialchars($description) ?>">
    <link rel="canonical" href="<?= htmlspecialchars($canonical) ?>">
    <meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1">

    <!-- Open Graph / Facebook / WhatsApp / LinkedIn -->
    <meta property="og:type" content="<?= htmlspecialchars($ogType) ?>">
    <meta property="og:site_name" content="Art For Sale">
    <meta property="og:url" content="<?= htmlspecialchars($ogUrl) ?>">
    <meta property="og:title" content="<?= htmlspecialchars($ogTitle) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($ogDesc) ?>">
    <meta property="og:image" content="<?= htmlspecialchars($ogImage) ?>">
    <meta property="og:image:secure_url" content="<?= htmlspecialchars($ogImage) ?>">
    <meta property="og:image:alt" content="<?= htmlspecialchars($ogTitle) ?>">
    <meta property="og:locale" content="pt_BR">

    <!-- Twitter Cards -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:url" content="<?= htmlspecialchars($ogUrl) ?>">
    <meta name="twitter:title" content="<?= htmlspecialchars($ogTitle) ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($ogDesc) ?>">
    <meta name="twitter:image" content="<?= htmlspecialchars($ogImage) ?>">
    <meta name="twitter:image:alt" content="<?= htmlspecialchars($ogTitle) ?>">

    <?php 
    // Renderiza Blocos Schema.org JSON-LD
    foreach ($schemas as $sch): 
        if (!empty($sch)):
    ?>
    <script type="application/ld+json">
    <?= json_encode($sch, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>
    </script>
    <?php 
        endif;
    endforeach; 
}
