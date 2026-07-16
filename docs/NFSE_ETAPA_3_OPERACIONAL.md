# NFS-e — Etapa 3: Homologação operacional e UX

## Escopo

A emissão manual já foi homologada em produção. Esta etapa transforma a tela administrativa em um painel operacional, sem alterar payload fiscal, endpoints RL2, Worker, webhook, retry automático, migrations ou banco.

## Painel

A listagem administrativa passa a priorizar leitura operacional com as colunas: Data, Cliente, Cobrança, Valor, Status, Documento e Ações. O cliente aparece com nome/razão social em destaque e `CLI_ID` em cinza; a cobrança aparece como `Cobrança #ID`, valor e data de pagamento quando disponível.

## Documento e detalhes

Chaves de acesso e RequestIds são abreviados na tabela e possuem botão de cópia. O modal de detalhes mostra dados essenciais da emissão sem expor JSON bruto, payload completo, caminhos internos, token, certificado ou senha.

## Downloads protegidos

PDF e XML são servidos por ações autenticadas do controller (`nfse/pdf/{id}` e `nfse/xml/{id}`). O sistema valida login administrativo, existência do arquivo privado, caminho relativo controlado e content type correto. Arquivos não são servidos diretamente pelo servidor web.

## Reconsulta e cancelamento

A ação Reconsultar chama as consultas técnicas de XML, PDF e eventos, atualizando persistência local e logs sem reenviar `GeraDps.php`. O cancelamento é ação administrativa explícita com modal, código de motivo e descrição fiscal. Não há cancelamento automático.

## Aptidão e segurança

A tela mantém seleção dependente Cliente → Cobrança e mensagens de aptidão mais claras, com validação obrigatória no backend antes de qualquer chamada fiscal. Nenhum segredo, PFX, Bearer, path real, stack trace, XML/PDF integral ou JSON bruto da API é exibido.

## Logs

O log `storage/logs/nfse.log` registra operações `emitir`, `consultar_pdf`, `consultar_xml`, `consultar_eventos` e `cancelar` com RequestId, HTTP, duração, resultado e identificadores internos, usando append seguro e rotação simples já existente.

## Rollback

Como não há migration ou alteração de payload, o rollback é reverter este commit. Arquivos privados já gravados em `storage/nfse/` permanecem fora do Git e podem ser mantidos para auditoria fiscal.
