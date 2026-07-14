# Scripts operacionais

## deploy.sh

Script de deploy automático da produção na VPS.

Fluxo:

1. bloqueia deploys simultâneos;
2. exige árvore Git limpa;
3. atualiza a branch `main` para `origin/main`;
4. valida arquivos PHP críticos;
5. executa health check em `https://disparador.net/`;
6. registra o resultado em `storage/logs/deploy.log`.

O script é acionado pelo GitHub Actions por meio de uma chave SSH restrita.

O wrapper instalado em `/usr/local/bin/disparador-deploy` deve apenas encaminhar a execução para este arquivo.

Não executar como `root`.
