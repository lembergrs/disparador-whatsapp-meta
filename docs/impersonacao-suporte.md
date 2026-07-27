# Impersonação para suporte

O modo suporte troca a identidade da sessão inteira do navegador. Todas as abas que compartilham o mesmo cookie de sessão passam a representar o cliente até o retorno ao administrador ou o logout.

## Implantação

Antes de publicar o código, aplique manualmente `database/migrations/20260727_create_suporte_acessos.sql`. O script apenas cria a tabela interna de auditoria e não deve ser executado automaticamente pela aplicação.

## Operação

1. Um administrador inicia o acesso pela listagem de clientes.
2. O sistema registra a auditoria antes de trocar a identidade.
3. A sessão passa a usar o usuário principal ativo (`cliente_admin`, com fallback legado para `cliente`) e conserva a identidade administrativa original separadamente.
4. A faixa amarela permite retornar usando somente os dados confiáveis da sessão.
5. O logout encerra a auditoria e destrói toda a sessão, sem restaurar automaticamente o administrador.

Não existem sessões independentes por aba nesta versão. Para trabalhar simultaneamente como administrador e cliente, use navegadores ou perfis de navegador separados.
