# ART FOR SALE - Guia de Configuração e Conexão com Supabase

### 1. Criar o Projeto
1. Acesse o dashboard do Supabase: https://supabase.com/dashboard
2. Abra seu projeto ou crie um novo projeto com o nome **ART FOR SALE**.

### 2. Executar o Esquema do Banco de Dados
1. No menu lateral esquerdo do Supabase, clique em **SQL Editor** (`>_`).
2. Clique em **+ New query**.
3. Copie todo o conteúdo do arquivo [`supabase/schema.sql`](./schema.sql) e cole no editor.
4. Clique no botão verde **Run** para criar todas as tabelas (`artworks`, `artwork_images`, `categories`, `artists`, `inquiries`, etc.), triggers e políticas RLS.

### 3. Configurar o Storage de Imagens
1. No **SQL Editor**, abra uma nova consulta (**+ New query**).
2. Copie todo o conteúdo do arquivo [`supabase/storage_setup.sql`](./storage_setup.sql) e cole no editor.
3. Clique em **Run**. Isso criará:
   - Bucket público: `artworks` (limite de 10 MB, formatos JPG, JPEG, PNG, WEBP).
   - Políticas RLS: Visitantes (leitura pública), Administradores autenticados (envio, atualização e exclusão).
   - Organização de pastas: `artworks/{artwork_id}/principal/` e `artworks/{artwork_id}/gallery/`.
   - Garantia de que cada obra possua uma **única imagem principal**.

### 4. Configurar as Chaves no Projeto
1. No painel do Supabase, vá em **Project Settings** > **API**.
2. Copie a **Project URL** e a chave **anon public**.
3. No projeto local:
   - Cole as credenciais em [`assets/js/supabase-config.js`](../assets/js/supabase-config.js).
   - Caso utilize backend PHP, crie o arquivo `.env` na raiz baseado no [`.env.example`](../.env.example).
