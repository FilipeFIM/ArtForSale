/**
 * ==============================================================================
 * ART FOR SALE - Configurações Centrais do Supabase (Frontend)
 * Arquivo: /assets/js/supabase-config.js
 * 
 * SEGURANÇA:
 * - Utilize EXCLUSIVAMENTE a chave pública anônima (anon public key).
 * - NUNCA coloque a chave "service_role" neste ou em qualquer arquivo JavaScript.
 * - Toda a proteção do banco e storage é garantida pelo Row Level Security (RLS).
 * ==============================================================================
 */

const artSaleConfig = Object.freeze({
    /**
     * URL do seu projeto Supabase
     * Onde encontrar: Painel do Supabase > Project Settings > API > Project URL
     * Exemplo: 'https://abcdefghijklmno.supabase.co'
     */
    supabaseUrl: 'https://recmxyfocskxvzbbkoui.supabase.co',

    /**
     * Chave Pública Anônima (anon key)
     * Onde encontrar: Painel do Supabase > Project Settings > API > Project API keys > anon public
     * Exemplo: 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...'
     */
    supabaseAnonKey: 'sb_publishable_TvGU0LOBDvDdLyfGXNWbig_BFU6EIKR',

    /**
     * Tempo limite padrão para requisições de rede (milissegundos)
     */
    timeoutMs: 9000,

    /**
     * Nome do bucket de storage público para imagens
     */
    storageBucket: 'artworks',

    /**
     * Limite razoável de tamanho por arquivo (10 MB = 10 * 1024 * 1024 bytes)
     */
    maxImageSizeBytes: 10485760,

    /**
     * Tipos MIME e extensões aceitas para imagens
     */
    allowedMimeTypes: Object.freeze(['image/jpeg', 'image/jpg', 'image/png', 'image/webp']),
    allowedExtensions: Object.freeze(['jpg', 'jpeg', 'png', 'webp']),

    /**
     * Tipos padronizados de imagem de uma obra
     */
    allowedImageTypes: Object.freeze([
        'principal',
        'frontal',
        'lateral',
        'detalhe',
        'textura',
        'ambiente',
        'outras'
    ])
});

window.ARTFORSALE_CONFIG = artSaleConfig;
window.ARTSALE_CONFIG = artSaleConfig;
window.ARTSELL_CONFIG = artSaleConfig; // Mantido para compatibilidade retroativa
