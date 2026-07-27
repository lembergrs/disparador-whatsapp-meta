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

## Experiência de leitura pública

- O tempo estimado é calculado automaticamente pelo service a partir do texto sem HTML, considerando 220 palavras por minuto, arredondamento para cima e mínimo de um minuto. O mesmo valor alimenta o `timeRequired` (ISO 8601) do `BlogPosting`.
- A página mostra autor (com fallback para **Equipe Disparador.net**), publicação por extenso em português e atualização somente quando `ART_AtualizadoEm` for posterior a `ART_DataPublicacao`.
- O breadcrumb visual segue **Início > Blog > Categoria > Artigo** e complementa, sem duplicar, o `BreadcrumbList` estruturado.
- O sumário gerado para três ou mais subtítulos usa uma única lista: lateral e sticky no desktop, recolhível antes do conteúdo em telas menores.
- Ao final da leitura ficam o bloco institucional, compartilhamento por WhatsApp, LinkedIn e Facebook, cópia acessível da URL canônica, navegação anterior/próximo, **Leia também** e o CTA já usado no site.
- A folha pública trata imagens, tabelas, código, citações, listas e textos extensos de forma responsiva.

## Elegibilidade e navegação

Anterior, próximo e relacionados consideram somente artigos ativos, com status `publicado`, data de publicação já alcançada e categoria ativa. A navegação usa data de publicação e ID como desempate em uma consulta. **Leia também** prioriza a mesma categoria, limita-se a três itens e, quando possível, exclui o artigo atual e os vizinhos já exibidos.

Não há campos novos ou preenchimento administrativo: as melhorias são derivadas dos dados existentes. Preview, canonical, metadados sociais, JSON-LD, sitemap e rotas públicas permanecem no fluxo atual.
