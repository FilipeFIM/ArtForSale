# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

Colecionadores de arte, arquitetos, designers de interiores e apreciadores que buscam obras e imagens contemporâneas de alto impacto visual criadas e desenvolvidas por artistas com o auxílio de inteligência artificial para transformar residências, escritórios e projetos corporativos.

## Product Purpose

Apresentar, valorizar e intermediar a aquisição de obras de arte visuais exclusivas desenvolvidas por artistas utilizando IA como meio expressivo. O sucesso significa conectar compradores qualificados a peças com curadoria criteriosa através de uma experiência digital sofisticada, confiável e consultiva, com atendimento humano caloroso e personalizado.

## Positioning

Galeria de arte contemporânea unindo inteligência artificial e curadoria humana. Ao contrário de plataformas de bancos de imagens descartáveis ou marketplaces massificados de ilustrações genéricas, a Art For Sale posiciona as obras como peças de acervo de galeria, criadas por artistas com visão autoral e sensibilidade plástica, comercializadas sob consulta direta com os curadores.

## Operating Context

- Aplicação web fluida e de alto desempenho executada em desktops, notebooks, tablets e smartphones.
- Descoberta visual intuitiva com catálogo filtrável por categorias, busca instantânea e ordenação.
- Exibição imersiva da obra com especificações de técnicas, dimensões e lightbox de alta resolução para inspeção minuciosa de texturas.
- Atendimento consultivo direto integrado via WhatsApp ou E-mail com a equipe curatorial (Sra. Elisabeth e Felipe).
- Painel administrativo privado para gerenciamento ágil de acervo, artistas, categorias e banners do Hero.

## Capabilities and Constraints

- **Funcionalidades Confirmadas**:
  - Hero slider dinâmico personalizável via painel administrativo com imagens do acervo ou upload.
  - Catálogo completo com busca em tempo real, filtros dinâmicos por categoria, ordenação e paginação.
  - Página de detalhes da obra com dados técnicos completos, galeria de ângulos/miniaturas e zoom em tela cheia.
  - Sistema de favoritos local (`localStorage`) com contador no cabeçalho.
  - Modal de consulta / "Tenho interesse nesta obra" com seleção de canal (Sra. Elisabeth, Felipe ou E-mail) e pré-preenchimento contextualizado.
  - Painel de administração com autenticação por sessão, CRUD de obras, artistas, categorias e controle de slides.
- **Restrições Técnicas**:
  - Backend PHP nativo com persistência estruturada em arquivos JSON em `database/` (com suporte a fallback Supabase).
  - Frontend em Vanilla JavaScript modular e CSS arquitetado sem dependências de frameworks pesados desnecessários.
  - Preservação integral de todas as rotas, dados existentes e regras de negócio.

## Brand Commitments

- **Nome**: Art For Sale
- **Slogan**: *"Arte que transforma espaços."*
- **Curadoria & Atendimento**: Sra. Elisabeth (`(51) 99114-0044`), Felipe C (`(51) 99126-6414`) e e-mail `artforsale1944@gmail.com`.
- **Diretriz de Design Mandatória**: Interface distinta, profissional e com personalidade autêntica. Banimento explícito de estética genérica de IA (gradientes clichês roxo/ciano, cards flutuantes padronizados, ilustrações impessoais e microtextos robóticos).

## Evidence on Hand

- Acervo catalogado com obras em alta definição armazenadas em `assets/images/obras/` e persistidas em `database/local_artworks.json`.
- Categorias de arte ativas em `database/local_categories.json`.
- Artistas registrados em `database/local_artists.json`.
- Logotipo oficial em vetor em `assets/images/site/logo.svg`.
- Identidade visual estabelecida com harmonia de tons neutros refinados, preto profundo e toques dourados.

## Product Principles

1. **A Obra é a Protagonista**: A interface deve atuar como o espaço físico de uma galeria nobre — minimalista, elegante e silenciosa, garantindo que as cores, texturas e emoções de cada quadro sejam o centro de gravidade visual.
2. **Tecnologia com Alma Humana**: O uso de inteligência artificial é a ferramenta criativa dos artistas, mas a experiência do cliente e a confiança na compra são sustentadas pelo contato humano genuíno da curadoria.
3. **Artesanato Visual e Anti-Slop**: Tipografia expressiva, espaçamentos deliberados e materiais visuais consistentes. Cada elemento na tela deve parecer desenhado sob medida por um diretor de arte experiente.
4. **Fluidez e Acessibilidade Sem Concessões**: Carregamento veloz, contraste legível, navegação por teclado e experiência mobile cirurgicamente ajustada, sem tremores, quebras ou rolagem indesejada.

## Accessibility & Inclusion

- Conformidade com padrões WCAG 2.1 AA.
- Áreas de toque confortáveis em telas móveis (mínimo de 44x44px).
- Rotulagem semântica precisa com suporte a tecnologias assistivas (`aria-label`, `aria-expanded`).
- Hierarquia tipográfica com contraste nítido em todos os níveis de luminosidade.
