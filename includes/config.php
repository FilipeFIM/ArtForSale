<?php
/**
 * ART SELL - Configuração Global
 * Identidade: Galeria de Arte Premium
 * Slogan: Arte que transforma espaços.
 */

// Informações Gerais do Site
define('SITE_NAME', 'ART SELL');
define('SITE_SLOGAN', 'Arte que transforma espaços.');
define('SITE_YEAR', 2026);

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
        'whatsapp_link' => 'https://wa.me/555191140044?text=' . urlencode('Olá, Sra. Elisabeth. Gostaria de mais informações sobre as obras da Art Sell.')
    ],
    'felipe' => [
        'nome' => 'Felipe C',
        'cargo' => 'Atendimento & Vendas',
        'telefone' => '(51) 99126-6414',
        'whatsapp' => '5551991266414',
        'whatsapp_link' => 'https://wa.me/5551991266414?text=' . urlencode('Olá, Felipe. Gostaria de consultar uma obra na Art Sell.')
    ],
    'email' => [
        'endereco' => 'xxxx@gmail.com',
        'link' => 'mailto:xxxx@gmail.com?subject=' . urlencode('Consulta de Obras - Art Sell')
    ]
];

if (!isset($pathPrefix)) {
    $pathPrefix = '';
}

// Função auxiliar que resolve o arquivo da imagem (.jpg ou .svg de fallback) com suporte a pathPrefix
function get_image_url(string $path): string {
    global $pathPrefix;
    $cleanPath = ltrim($path, '/');
    $prefix = $pathPrefix ?? '';
    if (file_exists(BASE_PATH . '/' . $cleanPath)) {
        return $prefix . $cleanPath;
    }
    $svgPath = preg_replace('/\.(jpg|jpeg|png)$/i', '.svg', $cleanPath);
    if (file_exists(BASE_PATH . '/' . $svgPath)) {
        return $prefix . $svgPath;
    }
    return $prefix . $cleanPath;
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
        'procedencia' => 'Galeria Art Sell',
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
        'procedencia' => 'Galeria Art Sell',
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
        'procedencia' => 'Acervo da Galeria Art Sell',
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

// Função auxiliar para resgatar uma obra pelo ID ou retornar a principal (Horizontes Dourados)
function get_obra_by_id($id) {
    global $catalogoObras;
    $id = (int)$id;
    foreach ($catalogoObras as $obra) {
        if ($obra['id'] === $id) {
            return $obra;
        }
    }
    return !empty($catalogoObras) ? $catalogoObras[0] : null;
}

// Diferenciais da Galeria (A Art Sell)
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

