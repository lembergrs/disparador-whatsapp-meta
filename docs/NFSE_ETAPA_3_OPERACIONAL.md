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

O rollback operacional é reverter os commits do módulo e, se aplicada, reverter a migration de reemissão conforme plano de banco. Arquivos privados já gravados em `storage/nfse/` permanecem fora do Git e podem ser mantidos para auditoria fiscal.

## Parametrização fiscal

O painel operacional exibe aviso de configuração fiscal incompleta quando `NFSE_CODIGO_TRIBUTACAO_NACIONAL` ou `NFSE_DESCRICAO_SERVICO` estiver ausente. O botão de emissão permanece desabilitado enquanto faltar qualquer valor e a prévia fiscal mostra, para conferência administrativa, o código tributário e a descrição configurados. O código tributário nacional é enviado no payload da emissão quando resolvido pelo snapshot/configuração fiscal.

## Disponibilização ao cliente

A NFS-e é exibida diretamente na tela **Financeiro** do cliente, junto da cobrança correspondente. Não há menu fiscal separado para o cliente.

Para cada cobrança, o sistema carrega as emissões fiscais relevantes em lote e escolhe uma única emissão vigente: primeiro uma emissão ativa; se não existir, a emissão cancelada mais recente; se não houver histórico fiscal, a cobrança aparece como nota não emitida/pendente. Quando uma cobrança possui uma emissão cancelada antiga e uma nova emissão ativa/emitida, somente a nova emissão é apresentada como vigente.

Os status são simplificados para o cliente: **Não emitida**, **Pendente**, **Emitindo**, **Processando**, **Emitida** ou **Cancelada**. Erros técnicos não são expostos; nesses casos a tela mostra mensagens amigáveis como nota fiscal pendente ou em processamento.

Os botões **PDF** e **XML** só aparecem quando os arquivos já estão armazenados. Os links usam as rotas autenticadas `nfse/pdf/{id}` e `nfse/xml/{id}`; nunca há link direto para `storage/nfse` ou caminho interno.

A autorização dos downloads é validada no backend. Administradores podem baixar documentos pelo módulo protegido. Clientes só podem baixar documentos de emissões vinculadas ao próprio `CLI_ID` e a uma cobrança do mesmo cliente. Tentativas de acessar documento de outro cliente não revelam a existência do arquivo nem caminhos internos.
