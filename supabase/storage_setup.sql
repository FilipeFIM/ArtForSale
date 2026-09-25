-- ==============================================================================
-- ART FOR SALE - CONFIGURAÇÃO DO SUPABASE STORAGE E POLÍTICAS DE IMAGENS
-- Arquivo: /supabase/storage_setup.sql
-- Tecnologias: PostgreSQL 15+, Supabase Storage, Row Level Security (RLS)
-- Slogan: "Arte que transforma espaços."
-- ==============================================================================

-- ------------------------------------------------------------------------------
-- 1. CRIAÇÃO E CONFIGURAÇÃO DO BUCKET PÚBLICO "artworks"
-- ------------------------------------------------------------------------------
-- Limite de tamanho razoável: 10 MB (10.485.760 bytes) por arquivo
-- Formatos permitidos: JPG, JPEG, PNG, WEBP
-- ------------------------------------------------------------------------------

INSERT INTO storage.buckets (id, name, public, file_size_limit, allowed_mime_types)
VALUES (
    'artworks',
    'artworks',
    true,
    10485760, -- 10 MB
    ARRAY[
        'image/jpeg',
        'image/jpg',
        'image/png',
        'image/webp'
    ]
)
ON CONFLICT (id) DO UPDATE SET
    public = true,
    file_size_limit = 10485760,
    allowed_mime_types = ARRAY[
        'image/jpeg',
        'image/jpg',
        'image/png',
        'image/webp'
    ];

-- ------------------------------------------------------------------------------
-- 2. AJUSTES NA TABELA PUBLIC.ARTWORK_IMAGES
-- ------------------------------------------------------------------------------
-- Garante os campos exigidos:
-- image_url, storage_path, image_type, sort_order, is_primary
-- Tipos permitidos: principal, frontal, lateral, detalhe, textura, ambiente, outras
-- ------------------------------------------------------------------------------

-- Adiciona a coluna storage_path se ainda não existir
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 
        FROM information_schema.columns 
        WHERE table_schema = 'public' 
          AND table_name = 'artwork_images' 
          AND column_name = 'storage_path'
    ) THEN
        ALTER TABLE public.artwork_images ADD COLUMN storage_path TEXT;
    END IF;
END $$;

-- Adiciona / atualiza a constraint de tipos de imagem permitidos
ALTER TABLE public.artwork_images 
    DROP CONSTRAINT IF EXISTS check_artwork_image_type;

ALTER TABLE public.artwork_images 
    ADD CONSTRAINT check_artwork_image_type CHECK (
        image_type IN (
            'principal',
            'frontal',
            'lateral',
            'detalhe',
            'textura',
            'ambiente',
            'outras'
        )
    );

-- ------------------------------------------------------------------------------
-- 3. REGRA DE INTEGRIDADE: APENAS UMA IMAGEM PRINCIPAL POR OBRA
-- ------------------------------------------------------------------------------

-- Função Trigger: Se uma imagem for marcada como 'is_primary = true' ou tipo 'principal',
-- desmarca automaticamente qualquer outra imagem primária anterior daquela mesma obra.
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

-- Índice Parcial Único: Garante integridade no nível do banco de que não pode haver
-- mais de uma imagem com is_primary = true para o mesmo artwork_id
CREATE UNIQUE INDEX IF NOT EXISTS idx_artwork_images_single_primary
    ON public.artwork_images (artwork_id)
    WHERE is_primary = true;

-- ------------------------------------------------------------------------------
-- 4. POLÍTICAS DE ACESSO DO STORAGE (STORAGE.OBJECTS)
-- ------------------------------------------------------------------------------
-- Visitantes: Leitura pública das imagens no bucket 'artworks'.
-- Administradores: Envio (INSERT), Atualização (UPDATE) e Exclusão (DELETE).
-- Não permite uploads administrativos sem autenticação e autorização (is_admin).
-- Valida extensões JPG, JPEG, PNG, WEBP e estrutura de pastas:
--   artworks/{artwork_id}/principal/
--   artworks/{artwork_id}/gallery/
-- ------------------------------------------------------------------------------

-- A. VISITANTES: Podem visualizar imagens públicas
DROP POLICY IF EXISTS "Visitantes podem visualizar imagens públicas" ON storage.objects;
CREATE POLICY "Visitantes podem visualizar imagens públicas"
    ON storage.objects
    FOR SELECT
    TO public
    USING (bucket_id = 'artworks');

-- B. ADMINISTRADORES: Podem fazer upload de imagens
DROP POLICY IF EXISTS "Admins podem enviar imagens de obras" ON storage.objects;
CREATE POLICY "Admins podem enviar imagens de obras"
    ON storage.objects
    FOR INSERT
    TO authenticated
    WITH CHECK (
        bucket_id = 'artworks'
        AND public.is_admin()
        AND lower(storage.extension(name)) IN ('jpg', 'jpeg', 'png', 'webp')
        AND (
            -- Garante a organização em pastas: {artwork_id}/principal/... ou {artwork_id}/gallery/... ou categories/... ou artists/...
            (storage.foldername(name))[2] IN ('principal', 'gallery')
            OR (storage.foldername(name))[1] IN ('principal', 'gallery', 'categories', 'artists')
        )
    );

-- C. ADMINISTRADORES: Podem atualizar imagens existentes
DROP POLICY IF EXISTS "Admins podem atualizar imagens de obras" ON storage.objects;
CREATE POLICY "Admins podem atualizar imagens de obras"
    ON storage.objects
    FOR UPDATE
    TO authenticated
    USING (
        bucket_id = 'artworks'
        AND public.is_admin()
    )
    WITH CHECK (
        bucket_id = 'artworks'
        AND public.is_admin()
        AND lower(storage.extension(name)) IN ('jpg', 'jpeg', 'png', 'webp')
    );

-- D. ADMINISTRADORES: Podem excluir imagens
DROP POLICY IF EXISTS "Admins podem excluir imagens de obras" ON storage.objects;
CREATE POLICY "Admins podem excluir imagens de obras"
    ON storage.objects
    FOR DELETE
    TO authenticated
    USING (
        bucket_id = 'artworks'
        AND public.is_admin()
    );

-- ------------------------------------------------------------------------------
-- 5. FUNÇÕES AUXILIARES DE CAMINHO E URLs
-- ------------------------------------------------------------------------------

-- Gera o storage_path padronizado conforme regras de organização:
-- artworks/{artwork_id}/principal/ ou artworks/{artwork_id}/gallery/
CREATE OR REPLACE FUNCTION public.build_artwork_storage_path(
    p_artwork_id UUID,
    p_image_type VARCHAR,
    p_filename TEXT
)
RETURNS TEXT
LANGUAGE plpgsql
IMMUTABLE
AS $$
DECLARE
    v_folder TEXT;
    v_safe_filename TEXT;
BEGIN
    IF p_image_type = 'principal' THEN
        v_folder := 'principal';
    ELSE
        v_folder := 'gallery';
    END IF;

    -- Remove caracteres perigosos do nome do arquivo
    v_safe_filename := regexp_replace(p_filename, '[^a-zA-Z0-9._-]', '_', 'g');

    RETURN p_artwork_id::text || '/' || v_folder || '/' || v_safe_filename;
END;
$$;

-- View utilitária para o catálogo público com a imagem principal de cada obra
CREATE OR REPLACE VIEW public.view_artworks_cards AS
SELECT 
    a.id,
    a.name AS titulo,
    a.slug,
    a.technique AS tecnica,
    a.year AS ano,
    a.width,
    a.height,
    a.depth,
    a.code AS codigo,
    a.availability AS disponibilidade,
    a.featured AS destaque,
    a.active AS ativo,
    c.name AS categoria,
    c.slug AS categoria_slug,
    art.name AS artista,
    art.slug AS artista_slug,
    -- Imagem principal resolvida (prioriza is_primary = true, depois principal, depois a primeira da galeria)
    COALESCE(
        primary_img.image_url,
        first_img.image_url,
        'assets/images/obras/obra-horizontes-dourados.svg'
    ) AS imagem_principal_url,
    COALESCE(
        primary_img.storage_path,
        first_img.storage_path
    ) AS imagem_principal_storage_path
FROM public.artworks a
LEFT JOIN public.categories c ON c.id = a.category_id
LEFT JOIN public.artists art ON art.id = a.artist_id
LEFT JOIN LATERAL (
    SELECT img.image_url, img.storage_path
    FROM public.artwork_images img
    WHERE img.artwork_id = a.id 
      AND img.is_primary = true
    LIMIT 1
) primary_img ON true
LEFT JOIN LATERAL (
    SELECT img.image_url, img.storage_path
    FROM public.artwork_images img
    WHERE img.artwork_id = a.id
    ORDER BY img.sort_order ASC, img.created_at ASC
    LIMIT 1
) first_img ON true
WHERE a.active = true;

-- Permite leitura da view pública
GRANT SELECT ON public.view_artworks_cards TO anon, authenticated;
