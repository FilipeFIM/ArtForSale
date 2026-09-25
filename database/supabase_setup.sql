-- ==============================================================================
-- ART FOR SALE - Configuração Completa e Segura do Supabase
-- Arquivo: /database/supabase_setup.sql
-- Tecnologias: PostgreSQL 15+, Supabase Auth, Row Level Security (RLS)
-- Slogan: "Arte que transforma espaços."
-- 
-- POLÍTICAS DE SEGURANÇA E ACESSO IMPLEMENTADAS:
-- 1. Visitantes (anon):
--    - Podem apenas LER obras, categorias, artistas e imagens ativas.
--    - Podem apenas INSERIR mensagens de contato (contacts) e consultas (inquiries).
--    - NUNCA podem ler contatos ou consultas de terceiros.
--    - NUNCA podem cadastrar, editar ou excluir obras, imagens, categorias ou artistas.
--    - Podem visualizar imagens públicas no Storage ('artworks').
-- 
-- 2. Administradores (auth.users com profiles.role = 'admin'):
--    - Podem listar todas as obras (ativas e inativas), contatos e consultas.
--    - Podem criar, editar e excluir obras, artistas, categorias e imagens.
--    - Podem realizar upload, atualização e exclusão de arquivos no Storage.
-- ==============================================================================

-- ------------------------------------------------------------------------------
-- 1. EXTENSÕES NECESSÁRIAS
-- ------------------------------------------------------------------------------
CREATE EXTENSION IF NOT EXISTS "pgcrypto";
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";

-- ------------------------------------------------------------------------------
-- 2. CRIAÇÃO DAS TABELAS COM TIPAGEM E CONSTRAINTS
-- ------------------------------------------------------------------------------

-- Categorias
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

-- Artistas
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

-- Obras de Arte
CREATE TABLE IF NOT EXISTS public.artworks (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    artist_id UUID NOT NULL REFERENCES public.artists(id) ON DELETE RESTRICT,
    description TEXT,
    technique VARCHAR(255),
    year INTEGER,
    width NUMERIC(10, 2),
    height NUMERIC(10, 2),
    depth NUMERIC(10, 2),
    conservation_state VARCHAR(100) DEFAULT 'Excelente',
    code VARCHAR(50) UNIQUE,
    category_id UUID NOT NULL REFERENCES public.categories(id) ON DELETE RESTRICT,
    availability VARCHAR(50) NOT NULL DEFAULT 'available',
    provenance VARCHAR(255),
    location VARCHAR(255),
    featured BOOLEAN NOT NULL DEFAULT false,
    active BOOLEAN NOT NULL DEFAULT true,
    created_at TIMESTAMPTZ NOT NULL DEFAULT timezone('utc'::text, now()),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT timezone('utc'::text, now()),

    CONSTRAINT check_artwork_availability CHECK (
        availability IN ('available', 'reserved', 'sold', 'unavailable')
    )
);

-- Imagens das Obras (Galeria de Fotografias e Vistas)
CREATE TABLE IF NOT EXISTS public.artwork_images (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    artwork_id UUID NOT NULL REFERENCES public.artworks(id) ON DELETE CASCADE,
    image_url TEXT NOT NULL,
    storage_path TEXT,
    image_type VARCHAR(50) NOT NULL DEFAULT 'principal',
    sort_order INTEGER NOT NULL DEFAULT 0,
    is_primary BOOLEAN NOT NULL DEFAULT false,
    created_at TIMESTAMPTZ NOT NULL DEFAULT timezone('utc'::text, now()),

    CONSTRAINT check_artwork_image_type CHECK (
        image_type IN ('principal', 'frontal', 'lateral', 'detalhe', 'textura', 'ambiente', 'outras')
    )
);

-- Consultas de Interesse em Obras
CREATE TABLE IF NOT EXISTS public.inquiries (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    artwork_id UUID REFERENCES public.artworks(id) ON DELETE SET NULL,
    name VARCHAR(200) NOT NULL,
    email VARCHAR(255) NOT NULL,
    whatsapp VARCHAR(50),
    message TEXT,
    status VARCHAR(50) NOT NULL DEFAULT 'new',
    created_at TIMESTAMPTZ NOT NULL DEFAULT timezone('utc'::text, now()),

    CONSTRAINT check_inquiry_status CHECK (
        status IN ('new', 'in_progress', 'completed')
    )
);

-- Mensagens de Contato Geral
CREATE TABLE IF NOT EXISTS public.contacts (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(200) NOT NULL,
    email VARCHAR(255) NOT NULL,
    whatsapp VARCHAR(50),
    subject VARCHAR(200),
    message TEXT NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'new',
    created_at TIMESTAMPTZ NOT NULL DEFAULT timezone('utc'::text, now()),

    CONSTRAINT check_contact_status CHECK (
        status IN ('new', 'viewed', 'answered')
    )
);

-- Perfis Administrativos Vinculados ao Supabase Auth
CREATE TABLE IF NOT EXISTS public.profiles (
    id UUID PRIMARY KEY REFERENCES auth.users(id) ON DELETE CASCADE,
    role VARCHAR(50) NOT NULL DEFAULT 'admin',
    name VARCHAR(200),
    created_at TIMESTAMPTZ NOT NULL DEFAULT timezone('utc'::text, now()),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT timezone('utc'::text, now()),

    CONSTRAINT check_profile_role CHECK (
        role IN ('admin')
    )
);

-- ------------------------------------------------------------------------------
-- 3. ÍNDICES DE BUSCA E INTEGRIDADE
-- ------------------------------------------------------------------------------
CREATE INDEX IF NOT EXISTS idx_categories_slug ON public.categories(slug);
CREATE INDEX IF NOT EXISTS idx_categories_active ON public.categories(active);
CREATE INDEX IF NOT EXISTS idx_artists_slug ON public.artists(slug);
CREATE INDEX IF NOT EXISTS idx_artists_active ON public.artists(active);
CREATE INDEX IF NOT EXISTS idx_artworks_slug ON public.artworks(slug);
CREATE INDEX IF NOT EXISTS idx_artworks_artist_id ON public.artworks(artist_id);
CREATE INDEX IF NOT EXISTS idx_artworks_category_id ON public.artworks(category_id);
CREATE INDEX IF NOT EXISTS idx_artworks_active ON public.artworks(active);
CREATE INDEX IF NOT EXISTS idx_artwork_images_artwork_id ON public.artwork_images(artwork_id);
CREATE INDEX IF NOT EXISTS idx_artwork_images_is_primary ON public.artwork_images(is_primary);
CREATE UNIQUE INDEX IF NOT EXISTS idx_artwork_images_single_primary ON public.artwork_images (artwork_id) WHERE is_primary = true;
CREATE INDEX IF NOT EXISTS idx_inquiries_created_at ON public.inquiries(created_at DESC);
CREATE INDEX IF NOT EXISTS idx_contacts_created_at ON public.contacts(created_at DESC);

-- ------------------------------------------------------------------------------
-- 4. FUNÇÃO DE VERIFICAÇÃO DE ROLE ADMINISTRATIVA (is_admin)
-- ------------------------------------------------------------------------------
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

GRANT EXECUTE ON FUNCTION public.is_admin() TO authenticated, anon;

-- Trigger para criar perfil de admin automaticamente no registro de auth
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

-- Trigger para garantir que apenas uma imagem seja primária por obra
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
-- 5. HABILITAÇÃO RIGOROSA DE ROW LEVEL SECURITY (RLS)
-- ------------------------------------------------------------------------------
ALTER TABLE public.categories ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.artists ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.artworks ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.artwork_images ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.inquiries ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.contacts ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.profiles ENABLE ROW LEVEL SECURITY;

-- ------------------------------------------------------------------------------
-- 6. POLÍTICAS RLS PARA CATEGORIES
-- ------------------------------------------------------------------------------
DROP POLICY IF EXISTS "categories_select_policy" ON public.categories;
DROP POLICY IF EXISTS "Permitir leitura de categories" ON public.categories;
CREATE POLICY "categories_select_policy"
    ON public.categories FOR SELECT
    USING (active = true OR public.is_admin());

DROP POLICY IF EXISTS "categories_insert_admin" ON public.categories;
DROP POLICY IF EXISTS "Permitir insercao em categories" ON public.categories;
CREATE POLICY "categories_insert_admin"
    ON public.categories FOR INSERT
    WITH CHECK (public.is_admin());

DROP POLICY IF EXISTS "categories_update_admin" ON public.categories;
DROP POLICY IF EXISTS "Permitir atualizacao em categories" ON public.categories;
CREATE POLICY "categories_update_admin"
    ON public.categories FOR UPDATE
    USING (public.is_admin())
    WITH CHECK (public.is_admin());

DROP POLICY IF EXISTS "categories_delete_admin" ON public.categories;
DROP POLICY IF EXISTS "Permitir exclusao em categories" ON public.categories;
CREATE POLICY "categories_delete_admin"
    ON public.categories FOR DELETE
    USING (public.is_admin());

-- ------------------------------------------------------------------------------
-- 7. POLÍTICAS RLS PARA ARTISTS
-- ------------------------------------------------------------------------------
DROP POLICY IF EXISTS "artists_select_policy" ON public.artists;
DROP POLICY IF EXISTS "Permitir leitura de artists" ON public.artists;
CREATE POLICY "artists_select_policy"
    ON public.artists FOR SELECT
    USING (active = true OR public.is_admin());

DROP POLICY IF EXISTS "artists_insert_admin" ON public.artists;
DROP POLICY IF EXISTS "Permitir insercao em artists" ON public.artists;
CREATE POLICY "artists_insert_admin"
    ON public.artists FOR INSERT
    WITH CHECK (public.is_admin());

DROP POLICY IF EXISTS "artists_update_admin" ON public.artists;
DROP POLICY IF EXISTS "Permitir atualizacao em artists" ON public.artists;
CREATE POLICY "artists_update_admin"
    ON public.artists FOR UPDATE
    USING (public.is_admin())
    WITH CHECK (public.is_admin());

DROP POLICY IF EXISTS "artists_delete_admin" ON public.artists;
DROP POLICY IF EXISTS "Permitir exclusao em artists" ON public.artists;
CREATE POLICY "artists_delete_admin"
    ON public.artists FOR DELETE
    USING (public.is_admin());

-- ------------------------------------------------------------------------------
-- 8. POLÍTICAS RLS PARA ARTWORKS
-- ------------------------------------------------------------------------------
DROP POLICY IF EXISTS "artworks_select_policy" ON public.artworks;
DROP POLICY IF EXISTS "Permitir leitura publica de artworks" ON public.artworks;
CREATE POLICY "artworks_select_policy"
    ON public.artworks FOR SELECT
    USING (active = true OR public.is_admin());

DROP POLICY IF EXISTS "artworks_insert_admin" ON public.artworks;
DROP POLICY IF EXISTS "Permitir insercao em artworks" ON public.artworks;
CREATE POLICY "artworks_insert_admin"
    ON public.artworks FOR INSERT
    WITH CHECK (public.is_admin());

DROP POLICY IF EXISTS "artworks_update_admin" ON public.artworks;
DROP POLICY IF EXISTS "Permitir atualizacao em artworks" ON public.artworks;
CREATE POLICY "artworks_update_admin"
    ON public.artworks FOR UPDATE
    USING (public.is_admin())
    WITH CHECK (public.is_admin());

DROP POLICY IF EXISTS "artworks_delete_admin" ON public.artworks;
DROP POLICY IF EXISTS "Permitir exclusao em artworks" ON public.artworks;
CREATE POLICY "artworks_delete_admin"
    ON public.artworks FOR DELETE
    USING (public.is_admin());

-- ------------------------------------------------------------------------------
-- 9. POLÍTICAS RLS PARA ARTWORK_IMAGES
-- ------------------------------------------------------------------------------
DROP POLICY IF EXISTS "artwork_images_select_policy" ON public.artwork_images;
DROP POLICY IF EXISTS "Permitir leitura publica de artwork_images" ON public.artwork_images;
CREATE POLICY "artwork_images_select_policy"
    ON public.artwork_images FOR SELECT
    USING (
        EXISTS (
            SELECT 1 
            FROM public.artworks
            WHERE artworks.id = artwork_images.artwork_id
              AND (artworks.active = true OR public.is_admin())
        )
    );

DROP POLICY IF EXISTS "artwork_images_insert_admin" ON public.artwork_images;
DROP POLICY IF EXISTS "Permitir insercao em artwork_images" ON public.artwork_images;
CREATE POLICY "artwork_images_insert_admin"
    ON public.artwork_images FOR INSERT
    WITH CHECK (public.is_admin());

DROP POLICY IF EXISTS "artwork_images_update_admin" ON public.artwork_images;
DROP POLICY IF EXISTS "Permitir atualizacao em artwork_images" ON public.artwork_images;
CREATE POLICY "artwork_images_update_admin"
    ON public.artwork_images FOR UPDATE
    USING (public.is_admin())
    WITH CHECK (public.is_admin());

DROP POLICY IF EXISTS "artwork_images_delete_admin" ON public.artwork_images;
DROP POLICY IF EXISTS "Permitir exclusao em artwork_images" ON public.artwork_images;
CREATE POLICY "artwork_images_delete_admin"
    ON public.artwork_images FOR DELETE
    USING (public.is_admin());

-- ------------------------------------------------------------------------------
-- 10. POLÍTICAS RLS PARA INQUIRIES (CONSULTAS DE OBRAS)
-- Visitantes podem apenas enviar (INSERT). NUNCA listar (SELECT) dados de terceiros.
-- ------------------------------------------------------------------------------
DROP POLICY IF EXISTS "inquiries_insert_public" ON public.inquiries;
CREATE POLICY "inquiries_insert_public"
    ON public.inquiries FOR INSERT
    WITH CHECK (true);

DROP POLICY IF EXISTS "inquiries_select_admin" ON public.inquiries;
CREATE POLICY "inquiries_select_admin"
    ON public.inquiries FOR SELECT
    USING (public.is_admin());

DROP POLICY IF EXISTS "inquiries_update_admin" ON public.inquiries;
CREATE POLICY "inquiries_update_admin"
    ON public.inquiries FOR UPDATE
    USING (public.is_admin())
    WITH CHECK (public.is_admin());

DROP POLICY IF EXISTS "inquiries_delete_admin" ON public.inquiries;
CREATE POLICY "inquiries_delete_admin"
    ON public.inquiries FOR DELETE
    USING (public.is_admin());

-- ------------------------------------------------------------------------------
-- 11. POLÍTICAS RLS PARA CONTACTS (MENSAGENS DE CONTATO)
-- Visitantes podem apenas enviar (INSERT). NUNCA listar (SELECT) mensagens de terceiros.
-- ------------------------------------------------------------------------------
DROP POLICY IF EXISTS "contacts_insert_public" ON public.contacts;
CREATE POLICY "contacts_insert_public"
    ON public.contacts FOR INSERT
    WITH CHECK (true);

DROP POLICY IF EXISTS "contacts_select_admin" ON public.contacts;
CREATE POLICY "contacts_select_admin"
    ON public.contacts FOR SELECT
    USING (public.is_admin());

DROP POLICY IF EXISTS "contacts_update_admin" ON public.contacts;
CREATE POLICY "contacts_update_admin"
    ON public.contacts FOR UPDATE
    USING (public.is_admin())
    WITH CHECK (public.is_admin());

DROP POLICY IF EXISTS "contacts_delete_admin" ON public.contacts;
CREATE POLICY "contacts_delete_admin"
    ON public.contacts FOR DELETE
    USING (public.is_admin());

-- ------------------------------------------------------------------------------
-- 12. POLÍTICAS RLS PARA PROFILES
-- ------------------------------------------------------------------------------
DROP POLICY IF EXISTS "profiles_select_policy" ON public.profiles;
CREATE POLICY "profiles_select_policy"
    ON public.profiles FOR SELECT
    USING (auth.uid() = id OR public.is_admin());

DROP POLICY IF EXISTS "profiles_insert_policy" ON public.profiles;
CREATE POLICY "profiles_insert_policy"
    ON public.profiles FOR INSERT
    WITH CHECK (auth.uid() = id OR public.is_admin());

DROP POLICY IF EXISTS "profiles_update_policy" ON public.profiles;
CREATE POLICY "profiles_update_policy"
    ON public.profiles FOR UPDATE
    USING (public.is_admin())
    WITH CHECK (public.is_admin());

DROP POLICY IF EXISTS "profiles_delete_admin" ON public.profiles;
CREATE POLICY "profiles_delete_admin"
    ON public.profiles FOR DELETE
    USING (public.is_admin());

-- ------------------------------------------------------------------------------
-- 13. CONFIGURAÇÃO E POLÍTICAS DO SUPABASE STORAGE (BUCKET 'artworks')
-- ------------------------------------------------------------------------------
INSERT INTO storage.buckets (id, name, public, file_size_limit, allowed_mime_types)
VALUES (
    'artworks',
    'artworks',
    true,
    10485760, -- Limite de 10 MB por arquivo
    ARRAY['image/jpeg', 'image/jpg', 'image/png', 'image/webp']
)
ON CONFLICT (id) DO UPDATE SET
    public = true,
    file_size_limit = 10485760,
    allowed_mime_types = ARRAY['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];

-- Visitantes: apenas visualização pública das imagens
DROP POLICY IF EXISTS "Visualizacao publica de imagens de obras" ON storage.objects;
DROP POLICY IF EXISTS "Visitantes podem visualizar imagens públicas" ON storage.objects;
CREATE POLICY "Visitantes podem visualizar imagens públicas"
    ON storage.objects FOR SELECT
    TO public
    USING (bucket_id = 'artworks');

-- Administradores: upload apenas autenticado e validado
DROP POLICY IF EXISTS "Upload de imagens de obras" ON storage.objects;
DROP POLICY IF EXISTS "Admins podem enviar imagens de obras" ON storage.objects;
CREATE POLICY "Admins podem enviar imagens de obras"
    ON storage.objects FOR INSERT
    TO authenticated
    WITH CHECK (
        bucket_id = 'artworks'
        AND public.is_admin()
        AND lower(storage.extension(name)) IN ('jpg', 'jpeg', 'png', 'webp')
    );

-- Administradores: atualização
DROP POLICY IF EXISTS "Atualizacao de imagens de obras" ON storage.objects;
DROP POLICY IF EXISTS "Admins podem atualizar imagens de obras" ON storage.objects;
CREATE POLICY "Admins podem atualizar imagens de obras"
    ON storage.objects FOR UPDATE
    TO authenticated
    USING (bucket_id = 'artworks' AND public.is_admin())
    WITH CHECK (
        bucket_id = 'artworks'
        AND public.is_admin()
        AND lower(storage.extension(name)) IN ('jpg', 'jpeg', 'png', 'webp')
    );

-- Administradores: exclusão
DROP POLICY IF EXISTS "Exclusao de imagens de obras" ON storage.objects;
DROP POLICY IF EXISTS "Admins podem excluir imagens de obras" ON storage.objects;
CREATE POLICY "Admins podem excluir imagens de obras"
    ON storage.objects FOR DELETE
    TO authenticated
    USING (bucket_id = 'artworks' AND public.is_admin());
