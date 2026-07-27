# Blog e artigos

## Implantação

Aplicar manualmente `database/migrations/20260727_create_blog_artigos.sql` antes do deploy. A aplicação não executa scripts SQL automaticamente.

Garantir permissão de escrita do processo PHP em `public/uploads/blog`. Imagens são validadas por tamanho, MIME real e estrutura antes de serem gravadas com nome aleatório.

## Rotas

### Públicas

- `GET /blog`
- `GET /blog?q=termo&pagina=2`
- `GET /blog/categoria/{slug}`
- `GET /blog/{slug}`
- `GET /sitemap.xml`

### Administrativas

- `GET artigoAdmin`
- `GET artigoAdmin/formulario[&id=]`
- `POST artigoAdmin/salvar`
- `POST artigoAdmin/publicar`
- `POST artigoAdmin/despublicar`
- `POST artigoAdmin/excluir`
- `GET artigoAdmin/preview`
- `POST artigoAdmin/uploadImagemConteudo`
- `POST artigoAdmin/salvarCategoria` e `excluirCategoria`
- `POST artigoAdmin/salvarTag` e `excluirTag`

## Arquitetura

`Artigo` concentra persistência, publicação, paginação e consultas públicas. `ArtigoCategoria` e `ArtigoTag` mantêm taxonomias independentes; `artigos_tags_relacao` implementa N:N. Sanitização, sumário/âncoras e upload ficam em services separados dos controllers.

Essa separação permite acrescentar futuramente versões, revisão editorial e provedores de geração assistida em entidades próprias, sem misturar rascunho editorial com a publicação pública. Nenhuma funcionalidade de IA foi implementada nesta versão.

O sitemap estático permanece como fallback, mas a regra prioritária do `.htaccess` direciona `/sitemap.xml` ao controller dinâmico, que inclui somente artigos ativos, publicados e cuja data de publicação já chegou.
