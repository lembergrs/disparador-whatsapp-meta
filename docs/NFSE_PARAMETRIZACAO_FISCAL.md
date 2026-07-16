# NFS-e — Parametrização fiscal

## Objetivo

Preparar o Disparador.net para trabalhar com código tributário nacional e descrição do serviço por configuração de ambiente, sem definir regra fiscal definitiva no código e sem alterar a API RL2 NFS-e nesta etapa.

## Variáveis

Novas variáveis do Disparador:

```env
NFSE_CODIGO_TRIBUTACAO_NACIONAL=
NFSE_DESCRICAO_SERVICO=Licenciamento de uso da plataforma
```

`NFSE_CODIGO_TRIBUTACAO_NACIONAL` deve permanecer vazio no repositório até confirmação contábil. `NFSE_DESCRICAO_SERVICO` pode receber uma descrição operacional de exemplo, sem duplicar automaticamente o nome da plataforma.

## Comportamento atual

O Disparador lê as configurações por `NfseConfigService`, mostra a prévia segura no painel administrativo e bloqueia emissão manual quando código ou descrição estiverem ausentes. O bloqueio ocorre antes da reserva de DPS e antes de qualquer chamada à API.

## Integração futura com rl2-nfse

A API RL2 NFS-e ainda será adaptada em etapa posterior para receber o código tributário nacional no payload. Até lá, o Disparador mantém um TODO explícito no `NfsePayloadBuilder` e não altera o contrato enviado para a API. A descrição `descServico` já passa a vir da configuração do Disparador, preservando compatibilidade com o contrato atual.

## Auditoria histórica

Nesta etapa não há migration e não são criados campos novos. A descrição usada continua no payload/retorno sanitizado já persistido pela emissão. Quando a API aceitar o código parametrizado, deve-se reavaliar se uma migration pequena será necessária para preservar historicamente o código e a descrição efetivamente enviados.

## Restrições

Não há alteração de payload fiscal além da origem configurável da descrição já existente, não há automação, não há Worker, não há Webhook, não há retry automático, não há emissão real e não há alteração da API `rl2-nfse`.
