-- ==============================================================================
-- ART FOR SALE - SCHEMA POSTGRESQL PARA SUPABASE
-- Arquivo: /supabase/schema.sql
-- Tecnologias: PostgreSQL 15+, Supabase Auth, Row Level Security (RLS)
-- Slogan: "Arte que transforma espaços."
-- ==============================================================================

-- ------------------------------------------------------------------------------
-- 1. EXTENSÕES
-- ------------------------------------------------------------------------------
CREATE EXTENSION IF NOT EXISTS "pgcrypto";
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";

-- ------------------------------------------------------------------------------
-- 2. TABELAS PRINCIPAIS
-- ------------------------------------------------------------------------------

-- TABELA: CATEGORIES (Categorias de Obras)
CREATE TABLE IF NOT EXISTS public.categories (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(150) NOT NULL,
    slug VARCHAR(150) NOT NULL UNIQUE,
    description TEXT,
    image_url TEXT,
    active BOOLEAN NOT NULL DEFAULT true,
    created_at TIMESTAMPTZ NOT NULL DEFAULT timezone('utc'::text, now()),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT timezone('utc'::text, now())
);

-- TABELA: ARTISTS (Artistas e Mestres do Acervo)
CREATE TABLE IF NOT EXISTS public.artists (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(200) NOT NULL,
    slug VARCHAR(200) NOT NULL UNIQUE,
    biography TEXT,
    image_url TEXT,
    active BOOLEAN NOT NULL DEFAULT true,
    created_at TIMESTAMPTZ NOT NULL DEFAULT timezone('utc'::text, now()),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT timezone('utc'::text, now())
);

-- TABELA: ARTWORKS (Obras de Arte do Catálogo)
CREATE TABLE IF NOT EXISTS public.artworks (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    artist_id UUID NOT NULL REFERENCES public.artists(id) ON DELETE RESTRICT,
    description TEXT,
    technique VARCHAR(255),
    year INTEGER,
    width NUMERIC(10, 2),        -- Dimensões em centímetros (largura)
    height NUMERIC(10, 2),       -- Dimensões em centímetros (altura)
    depth NUMERIC(10, 2),        -- Dimensões em centímetros (profundidade para esculturas/3D)
    conservation_state VARCHAR(100) DEFAULT 'Excelente',
    code VARCHAR(50) UNIQUE,     -- Ex: 'ASF-0012'
    category_id UUID NOT NULL REFERENCES public.categories(id) ON DELETE RESTRICT,
    availability VARCHAR(50) NOT NULL DEFAULT 'available',
    provenance VARCHAR(255),
    location VARCHAR(255),
    featured BOOLEAN NOT NULL DEFAULT false,
    active BOOLEAN NOT NULL DEFAULT true,
    created_at TIMESTAMPTZ NOT NULL DEFAULT timezone('utc'::text, now()),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT timezone('utc'::text, now()),

    -- Validação estrita de disponibilidade
    CONSTRAINT check_artwork_availability CHECK (
        availability IN ('available', 'reserved', 'sold', 'unavailable')
    )
);

-- TABELA: ARTWORK_IMAGES (Galeria de Vistas Fotográficas de Cada Obra)
CREATE TABLE IF NOT EXISTS public.artwork_images (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    artwork_id UUID NOT NULL REFERENCES public.artworks(id) ON DELETE CASCADE,
    image_url TEXT NOT NULL,
    storage_path TEXT,
    image_type VARCHAR(50) NOT NULL DEFAULT 'principal', -- principal, frontal, lateral, detalhe, textura, ambiente, outras
    sort_order INTEGER NOT NULL DEFAULT 0,
    is_primary BOOLEAN NOT NULL DEFAULT false,
    created_at TIMESTAMPTZ NOT NULL DEFAULT timezone('utc'::text, now()),

    -- Tipos padronizados de imagem de uma obra
    CONSTRAINT check_artwork_image_type CHECK (
        image_type IN ('principal', 'frontal', 'lateral', 'detalhe', 'textura', 'ambiente', 'outras')
    )
);

-- TABELA: INQUIRIES (Consultas de Interesse em Obras - "Tenho interesse nesta obra")
CREATE TABLE IF NOT EXISTS public.inquiries (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    artwork_id UUID REFERENCES public.artworks(id) ON DELETE SET NULL,
    name VARCHAR(200) NOT NULL,
    email VARCHAR(255) NOT NULL,
    whatsapp VARCHAR(50),
    message TEXT,
    status VARCHAR(50) NOT NULL DEFAULT 'new',
    created_at TIMESTAMPTZ NOT NULL DEFAULT timezone('utc'::text, now()),

    -- Validação de status do atendimento
    CONSTRAINT check_inquiry_status CHECK (
        status IN ('new', 'in_progress', 'completed')
    )
);

-- TABELA: CONTACTS (Mensagens Gerais de Contato da Galeria)
CREATE TABLE IF NOT EXISTS public.contacts (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(200) NOT NULL,
    email VARCHAR(255) NOT NULL,
    whatsapp VARCHAR(50),
    subject VARCHAR(200),
    message TEXT NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'new',
    created_at TIMESTAMPTZ NOT NULL DEFAULT timezone('utc'::text, now()),

    -- Validação de status da mensagem
    CONSTRAINT check_contact_status CHECK (
        status IN ('new', 'viewed', 'answered')
    )
);

-- TABELA: PROFILES (Perfis de Usuários com Nível de Acesso Administrativo)
-- Relaciona-se com o Supabase Auth (auth.users)
CREATE TABLE IF NOT EXISTS public.profiles (
    id UUID PRIMARY KEY REFERENCES auth.users(id) ON DELETE CASCADE,
    role VARCHAR(50) NOT NULL DEFAULT 'admin',
    name VARCHAR(200),
    created_at TIMESTAMPTZ NOT NULL DEFAULT timezone('utc'::text, now()),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT timezone('utc'::text, now()),

    -- Validação da role administrativa
    CONSTRAINT check_profile_role CHECK (
        role IN ('admin')
    )
);

-- ------------------------------------------------------------------------------
-- 3. ÍNDICES DE PERFORMANCE E INTEGRIDADE
-- ------------------------------------------------------------------------------
CREATE INDEX IF NOT EXISTS idx_categories_slug ON public.categories(slug);
CREATE INDEX IF NOT EXISTS idx_categories_active ON public.categories(active);

CREATE INDEX IF NOT EXISTS idx_artists_slug ON public.artists(slug);
CREATE INDEX IF NOT EXISTS idx_artists_active ON public.artists(active);

CREATE INDEX IF NOT EXISTS idx_artworks_slug ON public.artworks(slug);
CREATE INDEX IF NOT EXISTS idx_artworks_artist_id ON public.artworks(artist_id);
CREATE INDEX IF NOT EXISTS idx_artworks_category_id ON public.artworks(category_id);
CREATE INDEX IF NOT EXISTS idx_artworks_availability ON public.artworks(availability);
CREATE INDEX IF NOT EXISTS idx_artworks_featured ON public.artworks(featured);
CREATE INDEX IF NOT EXISTS idx_artworks_active ON public.artworks(active);
CREATE INDEX IF NOT EXISTS idx_artworks_code ON public.artworks(code);

CREATE INDEX IF NOT EXISTS idx_artwork_images_artwork_id ON public.artwork_images(artwork_id);
CREATE INDEX IF NOT EXISTS idx_artwork_images_is_primary ON public.artwork_images(is_primary);
CREATE INDEX IF NOT EXISTS idx_artwork_images_sort_order ON public.artwork_images(sort_order);
CREATE UNIQUE INDEX IF NOT EXISTS idx_artwork_images_single_primary ON public.artwork_images (artwork_id) WHERE is_primary = true;

CREATE INDEX IF NOT EXISTS idx_inquiries_artwork_id ON public.inquiries(artwork_id);
CREATE INDEX IF NOT EXISTS idx_inquiries_status ON public.inquiries(status);
CREATE INDEX IF NOT EXISTS idx_inquiries_created_at ON public.inquiries(created_at DESC);

CREATE INDEX IF NOT EXISTS idx_contacts_status ON public.contacts(status);
CREATE INDEX IF NOT EXISTS idx_contacts_created_at ON public.contacts(created_at DESC);

CREATE INDEX IF NOT EXISTS idx_profiles_role ON public.profiles(role);

-- ------------------------------------------------------------------------------
-- 4. FUNÇÕES AUXILIARES DE SEGURANÇA E AUTOMAÇÃO
-- ------------------------------------------------------------------------------

-- Função para atualizar automaticamente a coluna updated_at
CREATE OR REPLACE FUNCTION public.handle_updated_at()
RETURNS TRIGGER
LANGUAGE plpgsql
AS $$
BEGIN
    NEW.updated_at = timezone('utc'::text, now());
    RETURN NEW;
END;
$$;

-- Função de Segurança: Verifica se o usuário autenticado possui role 'admin'
-- Executada com SECURITY DEFINER para ler profiles com segurança e sem loops de RLS
CREATE OR REPLACE FUNCTION public.is_admin()
RETURNS BOOLEAN
LANGUAGE sql
STABLE
SECURITY DEFINER
SET search_path = public
AS $$
    SELECT EXISTS (
        SELECT 1 
        FROM public.profiles
        WHERE id = auth.uid()
          AND role = 'admin'
    );
$$;

-- Permite que usuários anon e authenticated chamem a função is_admin()
GRANT EXECUTE ON FUNCTION public.is_admin() TO authenticated, anon;

-- Trigger para criar perfil automaticamente quando um usuário for registrado no Supabase Auth
CREATE OR REPLACE FUNCTION public.handle_new_auth_user()
RETURNS TRIGGER
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = public
AS $$
BEGIN
    INSERT INTO public.profiles (id, name, role)
    VALUES (
        NEW.id,
        COALESCE(NEW.raw_user_meta_data->>'name', split_part(NEW.email, '@', 1)),
        'admin'
    )
    ON CONFLICT (id) DO NOTHING;
    RETURN NEW;
END;
$$;

DROP TRIGGER IF EXISTS on_auth_user_created ON auth.users;
CREATE TRIGGER on_auth_user_created
    AFTER INSERT ON auth.users
    FOR EACH ROW EXECUTE FUNCTION public.handle_new_auth_user();

-- Triggers de updated_at para tabelas com timestamp de atualização
DROP TRIGGER IF EXISTS trigger_categories_updated_at ON public.categories;
CREATE TRIGGER trigger_categories_updated_at
    BEFORE UPDATE ON public.categories
    FOR EACH ROW EXECUTE FUNCTION public.handle_updated_at();

DROP TRIGGER IF EXISTS trigger_artists_updated_at ON public.artists;
CREATE TRIGGER trigger_artists_updated_at
    BEFORE UPDATE ON public.artists
    FOR EACH ROW EXECUTE FUNCTION public.handle_updated_at();

DROP TRIGGER IF EXISTS trigger_artworks_updated_at ON public.artworks;
CREATE TRIGGER trigger_artworks_updated_at
    BEFORE UPDATE ON public.artworks
    FOR EACH ROW EXECUTE FUNCTION public.handle_updated_at();

DROP TRIGGER IF EXISTS trigger_profiles_updated_at ON public.profiles;
CREATE TRIGGER trigger_profiles_updated_at
    BEFORE UPDATE ON public.profiles
    FOR EACH ROW EXECUTE FUNCTION public.handle_updated_at();

-- Trigger para garantir que apenas uma imagem seja principal por obra
CREATE OR REPLACE FUNCTION public.handle_single_primary_artwork_image()
RETURNS TRIGGER
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = public
AS $$
BEGIN
    -- Se a imagem estiver sendo desmarcada como principal, ajusta o tipo para 'outras' e encerra sem refispar
    IF NEW.is_primary = false THEN
        IF NEW.image_type = 'principal' THEN
            NEW.image_type := 'outras';
        END IF;
        RETURN NEW;
    END IF;

    -- Se for marcada como principal ou tiver image_type = 'principal'
    IF NEW.is_primary = true OR NEW.image_type = 'principal' THEN
        NEW.is_primary := true;
        NEW.image_type := 'principal';

        -- Desmarca todas as outras imagens da mesma obra como principal
        UPDATE public.artwork_images
        SET is_primary = false,
            image_type = CASE WHEN image_type = 'principal' THEN 'outras' ELSE image_type END
        WHERE artwork_id = NEW.artwork_id
          AND id <> COALESCE(NEW.id, '00000000-0000-0000-0000-000000000000'::uuid)
          AND (is_primary = true OR image_type = 'principal');
    END IF;

    RETURN NEW;
END;
$$;

DROP TRIGGER IF EXISTS trigger_single_primary_artwork_image ON public.artwork_images;
CREATE TRIGGER trigger_single_primary_artwork_image
    BEFORE INSERT OR UPDATE OF is_primary, image_type ON public.artwork_images
    FOR EACH ROW
    EXECUTE FUNCTION public.handle_single_primary_artwork_image();

-- ------------------------------------------------------------------------------
-- 5. ROW LEVEL SECURITY (RLS) - POLÍTICAS DE SEGURANÇA E ACESSO
-- ------------------------------------------------------------------------------

-- Habilita RLS em todas as tabelas
ALTER TABLE public.categories ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.artists ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.artworks ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.artwork_images ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.inquiries ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.contacts ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.profiles ENABLE ROW LEVEL SECURITY;

-- ==============================================================================
-- POLÍTICAS: CATEGORIES
-- Visitantes: SELECT somente ativas.
-- Admin: SELECT todas, INSERT, UPDATE, DELETE.
-- ==============================================================================
DROP POLICY IF EXISTS "categories_select_policy" ON public.categories;
CREATE POLICY "categories_select_policy"
    ON public.categories
    FOR SELECT
    USING (active = true OR public.is_admin());

DROP POLICY IF EXISTS "categories_insert_admin" ON public.categories;
CREATE POLICY "categories_insert_admin"
    ON public.categories
    FOR INSERT
    WITH CHECK (public.is_admin());

DROP POLICY IF EXISTS "categories_update_admin" ON public.categories;
CREATE POLICY "categories_update_admin"
    ON public.categories
    FOR UPDATE
    USING (public.is_admin())
    WITH CHECK (public.is_admin());

DROP POLICY IF EXISTS "categories_delete_admin" ON public.categories;
CREATE POLICY "categories_delete_admin"
    ON public.categories
    FOR DELETE
    USING (public.is_admin());

-- ==============================================================================
-- POLÍTICAS: ARTISTS
-- Visitantes: SELECT somente ativos.
-- Admin: SELECT todos, INSERT, UPDATE, DELETE.
-- ==============================================================================
DROP POLICY IF EXISTS "artists_select_policy" ON public.artists;
CREATE POLICY "artists_select_policy"
    ON public.artists
    FOR SELECT
    USING (active = true OR public.is_admin());

DROP POLICY IF EXISTS "artists_insert_admin" ON public.artists;
CREATE POLICY "artists_insert_admin"
    ON public.artists
    FOR INSERT
    WITH CHECK (public.is_admin());

DROP POLICY IF EXISTS "artists_update_admin" ON public.artists;
CREATE POLICY "artists_update_admin"
    ON public.artists
    FOR UPDATE
    USING (public.is_admin())
    WITH CHECK (public.is_admin());

DROP POLICY IF EXISTS "artists_delete_admin" ON public.artists;
CREATE POLICY "artists_delete_admin"
    ON public.artists
    FOR DELETE
    USING (public.is_admin());

-- ==============================================================================
-- POLÍTICAS: ARTWORKS
-- Visitantes: SELECT somente ativas.
-- Admin: SELECT todas, INSERT, UPDATE, DELETE.
-- ==============================================================================
DROP POLICY IF EXISTS "artworks_select_policy" ON public.artworks;
CREATE POLICY "artworks_select_policy"
    ON public.artworks
    FOR SELECT
    USING (active = true OR public.is_admin());

DROP POLICY IF EXISTS "artworks_insert_admin" ON public.artworks;
CREATE POLICY "artworks_insert_admin"
    ON public.artworks
    FOR INSERT
    WITH CHECK (public.is_admin());

DROP POLICY IF EXISTS "artworks_update_admin" ON public.artworks;
CREATE POLICY "artworks_update_admin"
    ON public.artworks
    FOR UPDATE
    USING (public.is_admin())
    WITH CHECK (public.is_admin());

DROP POLICY IF EXISTS "artworks_delete_admin" ON public.artworks;
CREATE POLICY "artworks_delete_admin"
    ON public.artworks
    FOR DELETE
    USING (public.is_admin());

-- ==============================================================================
-- POLÍTICAS: ARTWORK_IMAGES
-- Visitantes: SELECT somente imagens pertencentes a obras ativas.
-- Admin: SELECT todas, INSERT, UPDATE, DELETE.
-- ==============================================================================
DROP POLICY IF EXISTS "artwork_images_select_policy" ON public.artwork_images;
CREATE POLICY "artwork_images_select_policy"
    ON public.artwork_images
    FOR SELECT
    USING (
        EXISTS (
            SELECT 1 
            FROM public.artworks
            WHERE artworks.id = artwork_images.artwork_id
              AND (artworks.active = true OR public.is_admin())
        )
    );

DROP POLICY IF EXISTS "artwork_images_insert_admin" ON public.artwork_images;
CREATE POLICY "artwork_images_insert_admin"
    ON public.artwork_images
    FOR INSERT
    WITH CHECK (public.is_admin());

DROP POLICY IF EXISTS "artwork_images_update_admin" ON public.artwork_images;
CREATE POLICY "artwork_images_update_admin"
    ON public.artwork_images
    FOR UPDATE
    USING (public.is_admin())
    WITH CHECK (public.is_admin());

DROP POLICY IF EXISTS "artwork_images_delete_admin" ON public.artwork_images;
CREATE POLICY "artwork_images_delete_admin"
    ON public.artwork_images
    FOR DELETE
    USING (public.is_admin());

-- ==============================================================================
-- POLÍTICAS: INQUIRIES
-- Visitantes: Apenas INSERT (envio de consulta pelo formulário).
-- Admin: SELECT, UPDATE (mudança de status), DELETE.
-- ==============================================================================
DROP POLICY IF EXISTS "inquiries_insert_public" ON public.inquiries;
CREATE POLICY "inquiries_insert_public"
    ON public.inquiries
    FOR INSERT
    WITH CHECK (true);

DROP POLICY IF EXISTS "inquiries_select_admin" ON public.inquiries;
CREATE POLICY "inquiries_select_admin"
    ON public.inquiries
    FOR SELECT
    USING (public.is_admin());

DROP POLICY IF EXISTS "inquiries_update_admin" ON public.inquiries;
CREATE POLICY "inquiries_update_admin"
    ON public.inquiries
    FOR UPDATE
    USING (public.is_admin())
    WITH CHECK (public.is_admin());

DROP POLICY IF EXISTS "inquiries_delete_admin" ON public.inquiries;
CREATE POLICY "inquiries_delete_admin"
    ON public.inquiries
    FOR DELETE
    USING (public.is_admin());

-- ==============================================================================
-- POLÍTICAS: CONTACTS
-- Visitantes: Apenas INSERT (envio de mensagem de contato).
-- Admin: SELECT, UPDATE, DELETE.
-- ==============================================================================
DROP POLICY IF EXISTS "contacts_insert_public" ON public.contacts;
CREATE POLICY "contacts_insert_public"
    ON public.contacts
    FOR INSERT
    WITH CHECK (true);

DROP POLICY IF EXISTS "contacts_select_admin" ON public.contacts;
CREATE POLICY "contacts_select_admin"
    ON public.contacts
    FOR SELECT
    USING (public.is_admin());

DROP POLICY IF EXISTS "contacts_update_admin" ON public.contacts;
CREATE POLICY "contacts_update_admin"
    ON public.contacts
    FOR UPDATE
    USING (public.is_admin())
    WITH CHECK (public.is_admin());

DROP POLICY IF EXISTS "contacts_delete_admin" ON public.contacts;
CREATE POLICY "contacts_delete_admin"
    ON public.contacts
    FOR DELETE
    USING (public.is_admin());

-- ==============================================================================
-- POLÍTICAS: PROFILES
-- Usuário autenticado: SELECT do próprio perfil.
-- Admin: SELECT de todos, UPDATE de perfis, DELETE de perfis.
-- ==============================================================================
DROP POLICY IF EXISTS "profiles_select_policy" ON public.profiles;
CREATE POLICY "profiles_select_policy"
    ON public.profiles
    FOR SELECT
    USING (auth.uid() = id OR public.is_admin());

DROP POLICY IF EXISTS "profiles_insert_policy" ON public.profiles;
CREATE POLICY "profiles_insert_policy"
    ON public.profiles
    FOR INSERT
    WITH CHECK (auth.uid() = id OR public.is_admin());

DROP POLICY IF EXISTS "profiles_update_policy" ON public.profiles;
CREATE POLICY "profiles_update_policy"
    ON public.profiles
    FOR UPDATE
    USING (auth.uid() = id OR public.is_admin())
    WITH CHECK (
        public.is_admin() OR (
            auth.uid() = id 
            AND role = (SELECT p.role FROM public.profiles p WHERE p.id = auth.uid())
        )
    );

DROP POLICY IF EXISTS "profiles_delete_admin" ON public.profiles;
CREATE POLICY "profiles_delete_admin"
    ON public.profiles
    FOR DELETE
    USING (public.is_admin());

-- ------------------------------------------------------------------------------
-- 6. CONFIGURAÇÃO DE STORAGE (BUCKET 'artworks' PARA IMAGENS)
-- Permite leitura pública das imagens e upload/gestão apenas por administradores
-- ------------------------------------------------------------------------------
INSERT INTO storage.buckets (id, name, public)
VALUES ('artworks', 'artworks', true)
ON CONFLICT (id) DO NOTHING;

DROP POLICY IF EXISTS "artworks_storage_public_select" ON storage.objects;
CREATE POLICY "artworks_storage_public_select"
    ON storage.objects
    FOR SELECT
    USING (bucket_id = 'artworks');

DROP POLICY IF EXISTS "artworks_storage_admin_insert" ON storage.objects;
CREATE POLICY "artworks_storage_admin_insert"
    ON storage.objects
    FOR INSERT
    WITH CHECK (
        bucket_id = 'artworks' 
        AND public.is_admin()
        AND lower(storage.extension(name)) IN ('jpg', 'jpeg', 'png', 'webp')
    );

DROP POLICY IF EXISTS "artworks_storage_admin_update" ON storage.objects;
CREATE POLICY "artworks_storage_admin_update"
    ON storage.objects
    FOR UPDATE
    USING (bucket_id = 'artworks' AND public.is_admin())
    WITH CHECK (
        bucket_id = 'artworks' 
        AND public.is_admin()
        AND lower(storage.extension(name)) IN ('jpg', 'jpeg', 'png', 'webp')
    );

DROP POLICY IF EXISTS "artworks_storage_admin_delete" ON storage.objects;
CREATE POLICY "artworks_storage_admin_delete"
    ON storage.objects
    FOR DELETE
    USING (bucket_id = 'artworks' AND public.is_admin());

-- ------------------------------------------------------------------------------
-- 7. CARGA INICIAL DE DADOS (SEED DATA IDEMPOTENTE DO ACERVO ART FOR SALE)
-- Popula as 8 categorias, 12 artistas, 12 obras canônicas e suas galerias
-- ------------------------------------------------------------------------------

-- Inserção das 8 Categorias Oficiais
INSERT INTO public.categories (id, name, slug, description, image_url, active)
VALUES
    ('c1000000-0000-0000-0000-000000000001', 'Arte Clássica', 'arte-classica', 'Obras com rigor técnico, proporção harmônica e reverência à grande tradição acadêmica e renascentista.', 'assets/images/obras/cat-arte-classica.svg', true),
    ('c1000000-0000-0000-0000-000000000002', 'Arte Moderna', 'arte-moderna', 'Expressões vanguardistas com desconstrução da forma, lirismo cromático e ruptura estética do século XX.', 'assets/images/obras/cat-arte-moderna.svg', true),
    ('c1000000-0000-0000-0000-000000000003', 'Arte Contemporânea', 'arte-contemporanea', 'Linguagens contemporâneas marcadas pela experimentação matérica, pigmentos nobres e reflexão crítica.', 'assets/images/obras/cat-arte-contemporanea.svg', true),
    ('c1000000-0000-0000-0000-000000000004', 'Paisagens', 'paisagens', 'Composições que celebram o horizonte, a luz crepuscular, águas serenas e atmosferas contemplativas.', 'assets/images/obras/cat-paisagens.svg', true),
    ('c1000000-0000-0000-0000-000000000005', 'Retratos', 'retratos', 'Estudos refinados da figura humana, intensidade psicológica e narrativa velada no olhar.', 'assets/images/obras/cat-retratos.svg', true),
    ('c1000000-0000-0000-0000-000000000006', 'Abstrato', 'abstrato', 'Campos ópticos hipnóticos, proporção áurea e harmonias visuais que despertam emoções e sensações puras.', 'assets/images/obras/cat-abstrato.svg', true),
    ('c1000000-0000-0000-0000-000000000007', 'Gravuras', 'gravuras', 'Tiragens de autor meticulosamente executadas em técnicas de metal, água-forte e ponta-seca sobre papéis nobres.', 'assets/images/obras/cat-gravuras.svg', true),
    ('c1000000-0000-0000-0000-000000000008', 'Esculturas', 'esculturas', 'Obras tridimensionais cinzeladas em mármore nobre e bronze com pátina por mestres da tridimensionalidade.', 'assets/images/obras/cat-esculturas.svg', true)
ON CONFLICT (slug) DO UPDATE 
SET name = EXCLUDED.name, description = EXCLUDED.description, image_url = EXCLUDED.image_url;

-- Inserção dos 12 Artistas Curados
INSERT INTO public.artists (id, name, slug, biography, active)
VALUES
    ('a1000000-0000-0000-0000-000000000001', 'Maria Fernandes', 'maria-fernandes', 'Pintora contemporânea reconhecida pelo lirismo visual e domínio das transições cromáticas entre tons ocres e profundidades noturnas.', true),
    ('a1000000-0000-0000-0000-000000000002', 'Carlos Menezes', 'carlos-menezes', 'Mestre na exploração geométrica dos cenários metropolitanos, contrastando paletas sóbrias com detalhes em ouro envelhecido.', true),
    ('a1000000-0000-0000-0000-000000000003', 'Juliana Costa', 'juliana-costa', 'Especialista em retratos de grande intensidade psicológica com forte influência do claro-escuro caravaggesco.', true),
    ('a1000000-0000-0000-0000-000000000004', 'Rafael Almeida', 'rafael-almeida', 'Pesquisador de pigmentos minerais e azul ultramarino em peças de grandes formatos que evocam a imensidão espacial.', true),
    ('a1000000-0000-0000-0000-000000000005', 'Helena Vianna', 'helena-vianna', 'Reinterpreta o rigor renascentista e a draperie acadêmica com estética atemporal voltada ao colecionismo moderno.', true),
    ('a1000000-0000-0000-0000-000000000006', 'Beatriz Drummond', 'beatriz-drummond', 'Compositora de sinfonias visuais através de acordes tonais vibrantes e ritmo gestual arrojado.', true),
    ('a1000000-0000-0000-0000-000000000007', 'Eduardo Fontes', 'eduardo-fontes', 'Dedicado à pesquisa matérica orgânica e minerais terrosos que dialogam com a arquitetura biofílica contemporânea.', true),
    ('a1000000-0000-0000-0000-000000000008', 'André Valente', 'andre-valente', 'Paisagista lírico mestre na captura de atmosferas serranas, névoas matinais e o calor acolhedor da luz dourada.', true),
    ('a1000000-0000-0000-0000-000000000009', 'Gabriela Siqueira', 'gabriela-siqueira', 'Retratista delicada com economia gestual refinada sobre madeiras nobres tratadas à têmpera.', true),
    ('a1000000-0000-0000-0000-000000000010', 'Lucas Brandão', 'lucas-brandao', 'Artista construtivista que conjuga proporção áurea, geometria ortogonal e delicadas incrustações em folha de ouro.', true),
    ('a1000000-0000-0000-0000-000000000011', 'Rodrigo Vasconcelos', 'rodrigo-vasconcelos', 'Gravurista tradicional com sólida reputação internacional em água-forte e ponta-seca sobre papéis 300g.', true),
    ('a1000000-0000-0000-0000-000000000012', 'Marcelo Duarte', 'marcelo-duarte', 'Escultor clássico que funde a solenidade greco-romana em mármore com a fluidez do bronze e pátinas nobres.', true)
ON CONFLICT (slug) DO UPDATE
SET name = EXCLUDED.name, biography = EXCLUDED.biography;

-- Inserção das 12 Obras de Arte
INSERT INTO public.artworks (
    id, name, slug, artist_id, description, technique, year, 
    width, height, depth, conservation_state, code, category_id, 
    availability, provenance, location, featured, active
) VALUES
    (
        'b1000000-0000-0000-0000-000000000001',
        'Horizontes Dourados',
        'horizontes-dourados',
        'a1000000-0000-0000-0000-000000000001',
        'Uma composição de expressivo lirismo visual onde a transição crepuscular irradia luminosidade sobre as águas serenas. O trabalho cromático em óleo sobre tela estabelece diálogos sutis entre o calor do ocre e a profundidade dos tons noturnos, conferindo ao ambiente uma presença imponente, contemplativa e acolhedora.',
        'Óleo sobre tela',
        2021,
        120.00,
        80.00,
        NULL,
        'Excelente',
        'ASF-0012',
        'c1000000-0000-0000-0000-000000000004',
        'available',
        'Coleção Particular',
        'Porto Alegre - RS',
        true,
        true
    ),
    (
        'b1000000-0000-0000-0000-000000000002',
        'Silêncio Urbano',
        'silencio-urbano',
        'a1000000-0000-0000-0000-000000000002',
        'Exploração geométrica das paisagens metropolitanas em silêncio. Através de tons monocromáticos e contrastes pontuais em ouro envelhecido, Menezes desconstrói a arquitetura das grandes cidades para evocar introspecção e equilíbrio estético.',
        'Acrílica e técnica mista sobre tela',
        2024,
        100.00,
        100.00,
        NULL,
        'Excelente',
        'ASF-0013',
        'c1000000-0000-0000-0000-000000000002',
        'available',
        'Acervo do Artista',
        'São Paulo - SP',
        true,
        true
    ),
    (
        'b1000000-0000-0000-0000-000000000003',
        'Essência',
        'essencia',
        'a1000000-0000-0000-0000-000000000003',
        'Um retrato de intensidade psicológica tocante. Com paleta intimista e jogos de luz e sombra de inspiração caravaggesca, Costa traduz a vulnerabilidade e a força interior humana com maestria singular.',
        'Óleo sobre linho belga',
        2024,
        90.00,
        60.00,
        NULL,
        'Excelente',
        'ASF-0014',
        'c1000000-0000-0000-0000-000000000005',
        'available',
        'Coleção Particular',
        'Rio de Janeiro - RJ',
        true,
        true
    ),
    (
        'b1000000-0000-0000-0000-000000000004',
        'Azul Profundo',
        'azul-profundo',
        'a1000000-0000-0000-0000-000000000004',
        'Imersão cromática em grandes dimensões que dialoga com as profundezas oceânicas e o infinito espacial. Camadas sucessivas de azul ultramarino e pigmentos minerais geram campos ópticos hipnóticos.',
        'Técnica mista e pigmentos minerais',
        2024,
        150.00,
        120.00,
        NULL,
        'Excelente',
        'ASF-0015',
        'c1000000-0000-0000-0000-000000000003',
        'available',
        'Galeria Art For Sale',
        'Belo Horizonte - MG',
        true,
        true
    ),
    (
        'b1000000-0000-0000-0000-000000000005',
        'Aura Clássica',
        'aura-classica',
        'a1000000-0000-0000-0000-000000000005',
        'Rigor acadêmico e estética renascentista reinterpretados para o colecionismo contemporâneo. Representação de virtudes atemporais através do estudo refinado da anatomia e draperie clássica.',
        'Óleo sobre tela',
        2023,
        100.00,
        70.00,
        NULL,
        'Excelente',
        'ASF-0016',
        'c1000000-0000-0000-0000-000000000001',
        'available',
        'Coleção Particular',
        'Curitiba - PR',
        false,
        true
    ),
    (
        'b1000000-0000-0000-0000-000000000006',
        'Ritmo Cromático',
        'ritmo-cromatico',
        'a1000000-0000-0000-0000-000000000006',
        'Sinfonia visual construída através de acordes tonais vibrantes e traços dinâmicos. A artista propõe uma experiência sensorial onde cada cor atua como uma nota musical em perfeita ressonância.',
        'Acrílica sobre tela',
        2024,
        90.00,
        90.00,
        NULL,
        'Excelente',
        'ASF-0017',
        'c1000000-0000-0000-0000-000000000002',
        'available',
        'Acervo do Artista',
        'Salvador - BA',
        false,
        true
    ),
    (
        'b1000000-0000-0000-0000-000000000007',
        'Terra e Memória',
        'terra-e-memoria',
        'a1000000-0000-0000-0000-000000000007',
        'Pesquisa matérica com texturas orgânicas e tons minerais que remetem à história geológica e à ancestralidade. Uma peça de presença sólida que harmoniza perfeitamente com projetos de arquitetura biofílica.',
        'Textura terrosa e polímeros sobre tela',
        2023,
        140.00,
        110.00,
        NULL,
        'Excelente',
        'ASF-0018',
        'c1000000-0000-0000-0000-000000000003',
        'available',
        'Coleção Particular',
        'Florianópolis - SC',
        false,
        true
    ),
    (
        'b1000000-0000-0000-0000-000000000008',
        'Crepúsculo Sereno',
        'crepusculo-sereno',
        'a1000000-0000-0000-0000-000000000008',
        'Paisagem lírica que registra a quietude das montanhas sob a luz dourada do entardecer. Valente demonstra domínio impecável da perspectiva atmosférica e da suavidade das névoas serranas.',
        'Óleo sobre tela',
        2024,
        110.00,
        75.00,
        NULL,
        'Excelente',
        'ASF-0019',
        'c1000000-0000-0000-0000-000000000004',
        'available',
        'Galeria Art For Sale',
        'Gramado - RS',
        false,
        true
    ),
    (
        'b1000000-0000-0000-0000-000000000009',
        'Olhar Silencioso',
        'olhar-silencioso',
        'a1000000-0000-0000-0000-000000000009',
        'Retrato contemporâneo carregado de sutilezas emocionais. O traço delicado e a economia gestual colocam em evidência a narrativa velada no olhar da figura retratada.',
        'Óleo e têmpera sobre madeira nobre',
        2023,
        75.00,
        50.00,
        NULL,
        'Excelente',
        'ASF-0020',
        'c1000000-0000-0000-0000-000000000005',
        'available',
        'Acervo do Artista',
        'Brasília - DF',
        false,
        true
    ),
    (
        'b1000000-0000-0000-0000-000000000010',
        'Harmonia Geométrica',
        'harmonia-geometrica',
        'a1000000-0000-0000-0000-000000000010',
        'Diálogo entre proporção áurea, linhas ortogonais e aplicações delicadas de folha de ouro. Uma obra de rigor conceitual construtivista que ilumina ambientes de arquitetura minimalista.',
        'Óleo e folha de ouro sobre tela',
        2024,
        120.00,
        100.00,
        NULL,
        'Excelente',
        'ASF-0021',
        'c1000000-0000-0000-0000-000000000006',
        'available',
        'Coleção Particular',
        'São Paulo - SP',
        false,
        true
    ),
    (
        'b1000000-0000-0000-0000-000000000011',
        'Atelier Histórico',
        'atelier-historico',
        'a1000000-0000-0000-0000-000000000011',
        'Gravura original em metal executada nas técnicas de água-forte e ponta-seca sobre papel algodão artesanal 300g. Registro meticuloso da atmosfera de ateliês de mestres gravuristas do século XIX.',
        'Gravura em metal (água-forte e ponta-seca)',
        2022,
        65.00,
        45.00,
        NULL,
        'Excelente',
        'ASF-0022',
        'c1000000-0000-0000-0000-000000000007',
        'available',
        'Tiragem Especial de Autor (7/25)',
        'Petrópolis - RJ',
        false,
        true
    ),
    (
        'b1000000-0000-0000-0000-000000000012',
        'Busto Imperial',
        'busto-imperial',
        'a1000000-0000-0000-0000-000000000012',
        'Escultura tridimensional em mármore nobre cinzelado à mão com pátina em bronze. Síntese imponente entre a solenidade clássica greco-romana e a fluidez orgânica da escultura contemporânea.',
        'Mármore esculpido e bronze com pátina',
        2023,
        35.00,
        55.00,
        30.00,
        'Excelente',
        'ASF-0023',
        'c1000000-0000-0000-0000-000000000008',
        'available',
        'Acervo da Galeria Art For Sale',
        'Porto Alegre - RS',
        false,
        true
    )
ON CONFLICT (slug) DO UPDATE
SET 
    name = EXCLUDED.name,
    artist_id = EXCLUDED.artist_id,
    description = EXCLUDED.description,
    technique = EXCLUDED.technique,
    year = EXCLUDED.year,
    width = EXCLUDED.width,
    height = EXCLUDED.height,
    depth = EXCLUDED.depth,
    category_id = EXCLUDED.category_id,
    availability = EXCLUDED.availability,
    provenance = EXCLUDED.provenance,
    location = EXCLUDED.location,
    featured = EXCLUDED.featured,
    active = EXCLUDED.active;

-- Inserção das Imagens da Galeria (Frontal, Lateral, Detalhe e Ambiente para as Obras)
INSERT INTO public.artwork_images (artwork_id, image_url, image_type, sort_order, is_primary)
VALUES
    -- Obra 1: Horizontes Dourados (4 vistas completas da galeria)
    ('b1000000-0000-0000-0000-000000000001', 'assets/images/obras/obra-horizontes-dourados.svg', 'frontal', 0, true),
    ('b1000000-0000-0000-0000-000000000001', 'assets/images/obras/obra-horizontes-dourados-lateral.svg', 'lateral', 1, false),
    ('b1000000-0000-0000-0000-000000000001', 'assets/images/obras/obra-horizontes-dourados-detalhe.svg', 'detalhe', 2, false),
    ('b1000000-0000-0000-0000-000000000001', 'assets/images/obras/obra-horizontes-dourados-ambiente.svg', 'ambiente', 3, false),

    -- Obra 2: Silêncio Urbano
    ('b1000000-0000-0000-0000-000000000002', 'assets/images/obras/obra-silencio-urbano.svg', 'frontal', 0, true),
    ('b1000000-0000-0000-0000-000000000002', 'assets/images/obras/obra-horizontes-dourados-lateral.svg', 'lateral', 1, false),
    ('b1000000-0000-0000-0000-000000000002', 'assets/images/obras/obra-horizontes-dourados-detalhe.svg', 'detalhe', 2, false),
    ('b1000000-0000-0000-0000-000000000002', 'assets/images/obras/obra-horizontes-dourados-ambiente.svg', 'ambiente', 3, false),

    -- Obra 3: Essência
    ('b1000000-0000-0000-0000-000000000003', 'assets/images/obras/obra-essencia.svg', 'frontal', 0, true),
    ('b1000000-0000-0000-0000-000000000003', 'assets/images/obras/obra-horizontes-dourados-lateral.svg', 'lateral', 1, false),
    ('b1000000-0000-0000-0000-000000000003', 'assets/images/obras/obra-horizontes-dourados-detalhe.svg', 'detalhe', 2, false),
    ('b1000000-0000-0000-0000-000000000003', 'assets/images/obras/obra-horizontes-dourados-ambiente.svg', 'ambiente', 3, false),

    -- Obra 4: Azul Profundo
    ('b1000000-0000-0000-0000-000000000004', 'assets/images/obras/obra-azul-profundo.svg', 'frontal', 0, true),
    ('b1000000-0000-0000-0000-000000000004', 'assets/images/obras/obra-horizontes-dourados-lateral.svg', 'lateral', 1, false),
    ('b1000000-0000-0000-0000-000000000004', 'assets/images/obras/obra-horizontes-dourados-detalhe.svg', 'detalhe', 2, false),
    ('b1000000-0000-0000-0000-000000000004', 'assets/images/obras/obra-horizontes-dourados-ambiente.svg', 'ambiente', 3, false),

    -- Obras 5 a 12: Imagens principais de cada categoria
    ('b1000000-0000-0000-0000-000000000005', 'assets/images/obras/cat-arte-classica.svg', 'frontal', 0, true),
    ('b1000000-0000-0000-0000-000000000006', 'assets/images/obras/cat-arte-moderna.svg', 'frontal', 0, true),
    ('b1000000-0000-0000-0000-000000000007', 'assets/images/obras/cat-arte-contemporanea.svg', 'frontal', 0, true),
    ('b1000000-0000-0000-0000-000000000008', 'assets/images/obras/cat-paisagens.svg', 'frontal', 0, true),
    ('b1000000-0000-0000-0000-000000000009', 'assets/images/obras/cat-retratos.svg', 'frontal', 0, true),
    ('b1000000-0000-0000-0000-000000000010', 'assets/images/obras/cat-abstrato.svg', 'frontal', 0, true),
    ('b1000000-0000-0000-0000-000000000011', 'assets/images/obras/cat-gravuras.svg', 'frontal', 0, true),
    ('b1000000-0000-0000-0000-000000000012', 'assets/images/obras/cat-esculturas.svg', 'frontal', 0, true)
ON CONFLICT DO NOTHING;

-- ==============================================================================
-- FIM DO ARQUIVO /supabase/schema.sql
-- ==============================================================================
