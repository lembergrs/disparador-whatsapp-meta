# Levantamento Técnico — Integração de NFS-e após confirmação de pagamento

Projeto: **Disparador.net**  
Data do levantamento: 2026-07-15  
Escopo: documentação técnica, sem implementação de código, sem migrations, sem alteração de banco, sem alteração de webhook e sem alteração da API RL2 NFS-e.

## 0. Validação inicial obrigatória

Comandos executados antes do levantamento:

```bash
git rev-parse HEAD
git log -5 --oneline --decorate
git status --short --branch
```

Resultado validado:

- HEAD: `df9e2e83d82e7731497fd3e68798edd610a812eb`.
- Branch atual: `work`.
- Diretório limpo no início: `## work`, sem arquivos modificados.

## 1. Arquivos e fontes analisadas

### 1.1 Disparador.net

Arquivos principais analisados para financeiro, pagamentos, recorrência, clientes, planos, trial, Worker, dashboard, controllers, models, views, Auth, config, liberação de plano, envio e downloads:

- `app/Controllers/AsaasController.php`: webhook Asaas, deduplicação de eventos, confirmação de pagamento e ativação de assinatura.
- `app/Controllers/FinanceiroController.php`: área financeira do cliente, escolha de plano, criação de assinatura pendente, criação de cobrança e sincronização com Asaas.
- `app/Controllers/FinanceiroAdminController.php`: painel financeiro administrativo, marcação manual de pagamento, cancelamento de cobrança, geração recorrente e processamento de vencimentos.
- `app/Services/FinanceiroRecorrenciaService.php`: geração de cobranças recorrentes, vencimento de cobranças e atualização de clientes/assinaturas inadimplentes.
- `app/Services/AsaasService.php`: contrato atual com Asaas para cliente, cobrança, consulta e QR Code Pix.
- `app/Models/Cobranca.php`: criação, listagem, paginação, marcação como pago, cancelamento, busca por provider payment id e registro de eventos do provedor.
- `app/Models/Cliente.php`: cadastro e campos cadastrais hoje disponíveis para tomador.
- `app/Models/Assinatura.php`: estados de assinatura, ativação, vencimento, cancelamento e próxima cobrança.
- `app/Models/Plano.php`: valores por ciclo e limites do plano.
- `app/Core/Auth.php`: bloqueio/liberação financeira, trial de avaliação e autorização cliente/admin.
- `app/Services/WorkerService.php`, `app/Services/WorkerDaemonRunner.php`, `worker.php`, `worker-daemon.php`: Worker contínuo atual, locks, ciclos, heartbeat e limites operacionais.
- `processar_vencimentos.php` e `gerar_cobrancas_recorrentes.php`: scripts CLI/cron financeiros.
- `app/Views/financeiro/index.php`: lista de faturas do cliente, pagamento e paginação AJAX.
- `app/Views/financeiro_admin/index.php`: gestão administrativa de planos, cobranças e clientes.
- `config/config.php`, `.env.example`: constantes de ambiente, Asaas, Worker e banco.
- Documentos correlatos: `docs/WORKER_LEVANTAMENTO_TECNICO.md`, `docs/WORKER_ETAPA_3_DAEMON.md`, `docs/auditoria-conexoes-banco.md`, `docs/politica-cancelamento-reembolso.md`, `docs/integracao-nfse-disparador.md`.

Pesquisas locais executadas com `rg` incluíram os termos solicitados: `pagamento`, `payment`, `invoice`, `financeiro`, `asaas`, `cobranca`, `cobrança`, `webhook`, `pago`, `confirmado`, `liberar`, `trial`, `renovação`, `recorrencia`, `recorrência`.

### 1.2 RL2 NFS-e

A primeira tentativa de leitura direta do repositório `lembergrs/rl2-nfse` falhou no workspace por restrição de rede/autorização (`CONNECT tunnel failed, response 403`). Para esta complementação, o contrato real informado pelo solicitante passa a ser a fonte obrigatória e suficiente para desenhar a integração sem inventar endpoints ou campos.

Contrato real incorporado ao levantamento:

- URL de produção: `https://api.disparador.net`.
- Autenticação: `Authorization: Bearer <API_AUTH_TOKEN>`.
- Health check público: `GET /`.
- Endpoints fiscais protegidos por Bearer token em `/acoes/*.php`.
- Métodos aceitos nos endpoints fiscais: `POST` e `OPTIONS`.
- Endpoints existentes: `GeraDps.php`, `CancelaNfse.php`, `ConsultaDanfse.php`, `ConsultaDpsChave.php`, `ConsultaNfseChave.php`, `ConsultaNfseEventos.php`.
- `ConsultaDanfse.php` retorna PDF binário em sucesso e JSON padronizado em erro.
- `ConsultaNfseChave.php` retorna XML em sucesso e JSON padronizado em erro.
- Todos os endpoints fiscais confirmados recebem `cert` e `senhaCert` no corpo JSON; esses campos são confidenciais e não podem ser persistidos em logs, histórico de requisição, registros de emissão ou tentativas.
- Timeouts configurados na API: conexão 10 segundos e requisição 30 segundos.

Pontos ainda pendentes não são o contrato de endpoints, mas decisões fiscais/operacionais do Disparador e possíveis evoluções da API, especialmente suporte a CPF do tomador, política de certificado e forma oficial de consulta/reconciliação por DPS/chave.

## 2. Fluxo atual de cobrança

### 2.1 Diagrama ASCII atual

```text
Cliente autenticado
   |
   | escolhe plano/ciclo em FinanceiroController::escolherPlano
   v
Transação local
   |-- atualiza clientes.CLI_Plano_DR e CLI_StatusPagamento='pendente'
   |-- cria/atualiza assinatura com ASS_Status='pendente'
   |-- cria cobrancas com COB_Status='pendente', COB_Provider='asaas'
   |-- chama AsaasService para criar/atualizar customer e payment
   |-- salva ids/link/payload do provedor
   v
Cliente abre link Asaas e paga
   |
   v
Asaas envia webhook
   |
   v
AsaasController::webhook
   |-- valida token asaas-access-token
   |-- registra log sanitizado em arquivo
   |-- busca cobranca por COB_ProviderPaymentId
   |-- registra cobranca_eventos para deduplicação
   |-- mapeia PAYMENT_RECEIVED/PAYMENT_CONFIRMED => COB_Status='pago'
   |-- marca COB_DataPagamento
   |-- Cliente::marcarPagamentoProviderConfirmado
   |-- Assinatura::ativar
   v
Área cliente/dashboard refletem cliente pago e assinatura ativa
```

### 2.2 Criação

- **Cliente**: cadastrado com `CLI_TipoPessoa`, `CLI_CPF_CNPJ`, `CLI_Nome`, `CLI_RazaoSocial`, `CLI_Email`, `CLI_Telefone`, `CLI_StatusPagamento` e outros campos administrativos.
- **Plano**: selecionado em `FinanceiroController::escolherPlano` com ciclo mensal/trimestral/semestral/anual.
- **Assinatura**: criada ou atualizada como `pendente`, com valor do ciclo, data de início e próxima cobrança.
- **Cobrança**: criada como `pendente`, forma `bolepix`, tipo `mensalidade`, vencimento inicial em 3 dias e provider `asaas`.
- **Asaas**: cliente e cobrança são sincronizados ainda dentro do fluxo de escolha de plano.

### 2.3 Vencimento

`FinanceiroRecorrenciaService::processarVencimentos` busca cobranças `pendente` com vencimento anterior ao dia atual, marca como `vencido`, marca assinatura vigente como `vencida` e altera `CLI_StatusPagamento` para `pendente`.

### 2.4 Pagamento e confirmação

Confirmação automática vem do webhook Asaas quando o evento é `PAYMENT_RECEIVED` ou `PAYMENT_CONFIRMED`. Confirmação manual vem do painel admin em `FinanceiroAdminController::marcarPago`.

### 2.5 Renovação

`FinanceiroRecorrenciaService::gerarCobrancasRecorrentes` seleciona assinaturas `ativa` com `ASS_DataProximaCobranca <= CURDATE()`, cria nova cobrança de mensalidade, evita duplicidade para assinatura/vencimento/tipo e avança a próxima cobrança.

### 2.6 Inadimplência

A inadimplência operacional é composta por cobrança vencida, assinatura vencida e cliente com status de pagamento pendente. `Auth::clienteLiberado` ainda permite acesso em tolerância financeira se houver cobrança vencida dentro de `FINANCEIRO_DIAS_TOLERANCIA_VENCIMENTO`; fora disso, cai para regra de avaliação/trial ou bloqueio.

### 2.7 Cancelamento

O painel admin cancela cobrança via `FinanceiroAdminController::cancelarCobranca`, que chama `Cobranca::cancelar` e grava `COB_Status='cancelado'`. Assinaturas podem ser canceladas no modelo `Assinatura::cancelar`, e mudança de plano encerra assinaturas vigentes.

### 2.8 Estorno

O webhook mapeia `PAYMENT_REFUNDED` para `COB_Status='cancelado'`. Não há automação fiscal hoje. Política de reembolso indica que estornos aprovados podem depender de ação administrativa manual no provedor.

## 3. Todos os pontos onde pagamento pode ser confirmado

| Ponto | Arquivo | Classe/método | Transação | Gatilho | Efeito |
|---|---|---|---|---|---|
| Webhook Asaas | `app/Controllers/AsaasController.php` | `AsaasController::processarEventoPagamento` | Não abre transação explícita; executa updates sequenciais via models | HTTP POST do Asaas com `PAYMENT_RECEIVED` ou `PAYMENT_CONFIRMED` | Registra evento, atualiza cobrança para `pago`, grava data de pagamento, marca cliente como pago/liberado e ativa assinatura. |
| Webhook Asaas duplicado | `app/Controllers/AsaasController.php` | ramo `registrarEventoProvider === 'duplicado'` | Não abre transação explícita | Reentrega do mesmo evento | Não atualiza cobrança novamente, mas chama ativação de assinatura em eventos confirmados para autocorreção idempotente parcial. |
| Marcação manual admin | `app/Controllers/FinanceiroAdminController.php` | `FinanceiroAdminController::marcarPago` | Sim, `beginTransaction`, `commit`, `rollBack` | Admin clica “Confirmar pagamento” | `Cobranca::marcarPago`, atualiza cliente para pago/ativo/liberado e ativa assinatura relacionada. |

Pontos que **não confirmam pagamento**, mas influenciam fluxo:

- `FinanceiroController::escolherPlano`: cria cobrança pendente e assinatura pendente; não confirma pagamento.
- `FinanceiroRecorrenciaService::gerarCobrancasRecorrentes`: gera cobranças futuras; não confirma pagamento.
- `FinanceiroRecorrenciaService::processarVencimentos`: marca vencidas; não confirma pagamento.
- `Cobranca::marcarPago`: é operação de baixo nível, só confirma quando chamada por controller/admin.

## 4. Fluxo proposto de NFS-e

### 4.1 Diagrama ASCII proposto

```text
Pagamento confirmado no Disparador
(webhook Asaas ou marcação manual admin)
   |
   | transação curta local
   |-- garante COB_Status='pago'
   |-- reserva registro nfse_emissoes com chave idempotente única
   |-- status='pendente'
   v
Commit local
   |
   v
Worker daemon existente / ciclo fiscal
   |
   |-- seleciona nfse_emissoes pendentes com lock lógico por UPDATE condicional
   |-- status='processando'
   |-- monta payload com dados congelados/sanitizados
   |-- chama API RL2 NFS-e usando contrato real
   |-- persiste resposta, número, código verificação, links PDF/XML ou erro
   v
Cliente consulta área financeira
   |-- status processando/emitida/erro
   |-- download PDF/XML via rota autenticada
   v
Admin fiscal
   |-- lista/filtra/consulta/reprocessa/cancela/reconcilia
```

### 4.2 Arquitetura recomendada

**Escolha única:** reserva síncrona curta no ponto de confirmação do pagamento + emissão assíncrona pelo Worker contínuo existente.

#### Alternativas comparadas

| Alternativa | Vantagens | Desvantagens | Decisão |
|---|---|---|---|
| Emitir dentro do webhook | Menor latência aparente; menos componentes | Webhook fica lento/frágil; timeouts geram reentregas; chamada fiscal externa dentro de fluxo crítico; difícil retry/reconciliação; risco de duplicidade | Rejeitada. Webhook deve apenas confirmar pagamento e reservar fila fiscal. |
| Emitir dentro da marcação manual admin | Simples para pagamento manual | Duplicaria lógica entre webhook/admin; admin ficaria bloqueado esperando API; sem retry robusto | Rejeitada. Deve usar o mesmo caminho assíncrono. |
| Script cron separado | Isolamento fiscal | Mais um processo agendado para operar; duplicaria controle de daemon; maior chance de concorrência se mal configurado | Rejeitada como primeira opção. Pode existir apenas comando manual de manutenção no futuro. |
| Novo Worker separado systemd | Isola carga fiscal da mensageria | Mais unidade systemd e mais operação; restrição pede reutilizar infraestrutura atual; risco de concorrência adicional | Rejeitada por simplicidade operacional inicial. |
| Worker contínuo existente com etapa fiscal própria | Reusa systemd, locks, heartbeat e backoff existentes; separa confirmação de emissão; permite retry e reconciliação; não cria infraestrutura | Exige cuidado para não acoplar à fila de WhatsApp; ciclo do Worker deve ter limite fiscal e métricas próprias | Escolhida. |

Impacto da escolha: a emissão não bloqueia pagamento, webhook, admin ou tela do cliente. A consistência é garantida por registro local idempotente e reconciliação posterior.

## 5. Ponto ideal para iniciar emissão

O início lógico deve ocorrer **após a confirmação local do pagamento**, nos dois pontos confirmadores:

1. `AsaasController::processarEventoPagamento`, apenas depois de reconhecer `PAYMENT_RECEIVED`/`PAYMENT_CONFIRMED` e antes/depois da ativação da assinatura, em transação curta futura.
2. `FinanceiroAdminController::marcarPago`, dentro da transação curta que já marca cobrança e cliente como pagos.

O que deve ser feito nesse ponto não é chamar a API fiscal, mas **reservar uma emissão**:

- localizar cobrança paga;
- criar `nfse_emissoes` se ainda não existir para a chave idempotente;
- gravar dados mínimos de correlação;
- status inicial `pendente`;
- `next_attempt_at = NOW()`;
- commit.

## 6. Idempotência

### 6.1 Garantia desejada

Uma cobrança (`cobrancas.COB_ID`) deve gerar **no máximo uma NFS-e emitida** para aquele pagamento confirmado, independentemente de:

- webhook duplicado;
- timeout de webhook;
- reprocessamento admin;
- concorrência entre Worker e script;
- dois processos Worker;
- falha local após sucesso remoto;
- sucesso remoto sem persistência local;
- estorno posterior;
- novo pagamento de nova cobrança.

### 6.2 Chave de idempotência local

Chave recomendada:

```text
nfse:cobranca:{COB_ID}:pagamento:{COB_DataPagamento normalizada ou COB_ProviderPaymentId}
```

Implementação de unicidade recomendada:

- `nfse_emissoes.NFE_COB_ID` com índice único para a regra principal “uma nota por cobrança”.
- `nfse_emissoes.NFE_IdempotencyKey` com índice único para auditoria local; não enviar como idempotência remota sem contrato futuro explícito.

Não há confirmação de idempotência nativa da API RL2 NFS-e. Portanto, a garantia primária deve ser local, por unicidade em `COB_ID`, `NFE_IdempotencyKey` e `numDPS`. O `numDPS` é apenas o identificador sequencial/local enviado na emissão; o contrato adicional confirma que `ConsultaDpsChave.php` exige `chaveDps`, não `numDPS`. Assim, `numDPS` sozinho não permite consulta nem reconciliação remota segura.

Geração recomendada de `numDPS`:

```text
numDPS = ano(4) + COB_ID zero-padded + dígito/ambiente se necessário
exemplo lógico: 2026 + COB_ID com 10 dígitos = 20260000012345
```

Requisitos: ser determinístico por cobrança, único no banco, gerado antes da primeira tentativa, persistido em `NFE_NumDps`, nunca regenerado em retry e usado somente como `dadosNota.numDPS` em `GeraDps.php`. Não usar `numDPS` como chave de consulta remota sem evolução explícita da API.

### 6.3 Concorrência

Reserva:

```text
INSERT nfse_emissoes (..., NFE_COB_ID, NFE_IdempotencyKey, NFE_Status='pendente')
ON DUPLICATE KEY não cria nova emissão
```

Processamento:

```text
UPDATE nfse_emissoes
SET NFE_Status='processando', NFE_LockOwner=?, NFE_LockUntil=DATE_ADD(NOW(), INTERVAL 10 MINUTE)
WHERE NFE_ID=?
  AND NFE_Status IN ('pendente','erro_temporario')
  AND (NFE_NextAttemptAt IS NULL OR NFE_NextAttemptAt <= NOW())
  AND (NFE_LockUntil IS NULL OR NFE_LockUntil < NOW())
```

Só o processo com `rowCount() = 1` chama a API. Não manter transação aberta durante HTTP.

### 6.4 Falha local após sucesso remoto

Cenário crítico: API emite a nota, mas o Disparador cai antes de persistir. Mitigação:

- salvar `NFE_RequestIdLocal` antes da chamada;
- enviar idempotency key/referência externa, se contrato permitir;
- em timeout ou queda, marcar `erro_temporario` com `NFE_ReconciliationRequired=1`;
- Worker de reconciliação consulta a API somente quando houver identificador consultável confirmado (`chaveDps`, `chaveAcesso`/`chaveNfse`/`idNota` ou outro retorno persistido); se não houver identificador consultável após timeout/perda de resposta, não reenviar `GeraDps.php` automaticamente e encaminhar para revisão administrativa.

## 7. Modelo de dados proposto, sem migration nesta tarefa

### 7.1 Tabela `nfse_emissoes`

| Campo | Tipo sugerido | Observação |
|---|---|---|
| `NFE_ID` | BIGINT PK | Identificador local. |
| `NFE_COB_ID` | BIGINT NOT NULL UNIQUE | Cobrança origem. |
| `NFE_CLI_ID` | BIGINT NOT NULL | Cliente/tomador. |
| `NFE_ASS_ID` | BIGINT NULL | Assinatura relacionada, se existir. |
| `NFE_PLA_ID` | BIGINT NULL | Plano relacionado. |
| `NFE_IdempotencyKey` | VARCHAR(191) NOT NULL UNIQUE | Chave local e eventual campo/header remoto. |
| `NFE_Status` | VARCHAR(40) NOT NULL | Estado fiscal. |
| `NFE_Tentativas` | INT NOT NULL DEFAULT 0 | Tentativas de emissão/consulta. |
| `NFE_MaxTentativas` | INT NOT NULL DEFAULT 8 | Limite configurável. |
| `NFE_NextAttemptAt` | DATETIME NULL | Próxima tentativa. |
| `NFE_LastAttemptAt` | DATETIME NULL | Última tentativa. |
| `NFE_LockOwner` | VARCHAR(100) NULL | Worker que reservou. |
| `NFE_LockUntil` | DATETIME NULL | TTL do lock lógico. |
| `NFE_Numero` | VARCHAR(100) NULL | Número da NFS-e. |
| `NFE_CodigoVerificacao` | VARCHAR(191) NULL | Código de verificação. |
| `NFE_NumDps` | VARCHAR(100) NOT NULL UNIQUE | Número DPS enviado para `GeraDps.php`. |
| `NFE_SerieDps` | VARCHAR(20) NULL | Série DPS fixa na API: `900`, persistida apenas para rastreabilidade se desejado. |
| `NFE_RequestIdEmissao` | VARCHAR(191) NULL | `requestId`/`X-Request-Id` retornado por `GeraDps.php`. |
| `NFE_RequestIdConsulta` | VARCHAR(191) NULL | Último `requestId`/`X-Request-Id` retornado por consultas PDF/XML/DPS/eventos. |
| `NFE_RequestIdCancelamento` | VARCHAR(191) NULL | `requestId`/`X-Request-Id` retornado por `CancelaNfse.php`. |
| `NFE_Operation` | VARCHAR(100) NULL | Última `operation` retornada pela API. |
| `NFE_IdDps` | VARCHAR(191) NULL | `data.idDps` retornado pela emissão; não presumir equivalência com `chaveDps`. |
| `NFE_ChaveDps` | VARCHAR(191) NULL | Chave DPS exigida por `ConsultaDpsChave.php`, se retornada/obtida. |
| `NFE_ChaveAcesso` | VARCHAR(191) NULL | `data.chaveAcesso`/`chaveNfse`/`idNota` usada para consultar PDF, XML, eventos e cancelar. |
| `NFE_RemoteId` | VARCHAR(191) NULL | ID remoto da API RL2, se houver além de `idDps`. |
| `NFE_RemoteStatus` | VARCHAR(80) NULL | Status remoto bruto. |
| `NFE_Competencia` | DATE NOT NULL | Competência do serviço. |
| `NFE_ValorServico` | DECIMAL(10,2) NOT NULL | Valor da cobrança. |
| `NFE_DescricaoServico` | TEXT NOT NULL | Descrição fiscal congelada. |
| `NFE_CodigoServico` | VARCHAR(50) NULL | Decisão fiscal pendente. |
| `NFE_Tributacao` | VARCHAR(100) NULL | Decisão fiscal pendente. |
| `NFE_TomadorSnapshot` | JSON/TEXT NOT NULL | Dados sanitizados do tomador no momento da emissão. |
| `NFE_RequestPayload` | JSON/TEXT NULL | Payload enviado, sem tokens. |
| `NFE_ResponsePayload` | JSON/TEXT NULL | Resposta sanitizada. |
| `NFE_LastErrorCode` | VARCHAR(100) NULL | Código local/remoto. |
| `NFE_LastErrorMessage` | TEXT NULL | Mensagem truncada e sanitizada. |
| `NFE_LastHttpStatus` | INT NULL | HTTP da API. |
| `NFE_XmlGZipB64` | LONGTEXT NULL | `nfseXmlGZipB64` retornado, se estratégia armazenar bruto compactado. |
| `NFE_XmlStoragePath` | TEXT NULL | Caminho local privado do XML oficial/descompactado. |
| `NFE_PdfStoragePath` | TEXT NULL | Caminho local privado do PDF/DANFSE. |
| `NFE_PdfSha256` | CHAR(64) NULL | Hash SHA-256 recomendado do PDF armazenado. |
| `NFE_XmlSha256` | CHAR(64) NULL | Hash SHA-256 recomendado do XML armazenado. |
| `NFE_PdfUrlRemota` | TEXT NULL | Link remoto se existir e for seguro. |
| `NFE_XmlUrlRemota` | TEXT NULL | Link remoto se existir e for seguro. |

| `NFE_DataEmissao` | DATETIME NULL | Data efetiva de emissão. |
| `NFE_DataCancelamento` | DATETIME NULL | Data de cancelamento. |
| `NFE_CancelamentoMotivo` | TEXT NULL | Motivo sanitizado. |
| `NFE_ReconciliationRequired` | TINYINT(1) DEFAULT 0 | Exige consulta. |
| `NFE_CreatedAt` | DATETIME NOT NULL | Criação. |
| `NFE_UpdatedAt` | DATETIME NOT NULL | Atualização. |

### 7.2 Tabela `nfse_eventos`

Registro append-only para auditoria.

| Campo | Uso |
|---|---|
| `NFEV_ID` | PK. |
| `NFE_ID` | FK emissão. |
| `COB_ID` | Cobrança. |
| `NFEV_Tipo` | `reservada`, `processando`, `request`, `response`, `erro`, `retry_agendado`, `consulta`, `cancelamento`, `download`, `admin_reprocessou`. |
| `NFEV_StatusAnterior` / `NFEV_StatusNovo` | Transição. |
| `NFEV_HttpStatus` | HTTP quando aplicável. |
| `NFEV_Codigo` | Código remoto/local. |
| `NFEV_Mensagem` | Mensagem sanitizada/truncada. |
| `NFEV_Payload` | JSON sanitizado. |
| `USU_ID` | Usuário/admin quando aplicável. |
| `NFEV_CreatedAt` | Data. |

### 7.3 Relacionamentos

```text
clientes.CLI_ID 1--N cobrancas.COB_ID 1--0/1 nfse_emissoes.NFE_ID 1--N nfse_eventos
planos.PLA_ID   1--N cobrancas
assinaturas.ASS_ID 1--N cobrancas
```

## 8. Contrato real da API RL2 NFS-e

### 8.1 Base, autenticação, métodos e envelopes

- URL base de produção: `https://api.disparador.net`.
- Autenticação dos endpoints fiscais: header `Authorization: Bearer <API_AUTH_TOKEN>`.
- O endpoint raiz `/` é público apenas para health check.
- Todos os endpoints fiscais ficam em `/acoes/*.php` e exigem Bearer token.
- Todos os endpoints fiscais confirmados recebem `cert` e `senhaCert` no corpo; esses segredos não podem ser registrados em logs nem persistidos em registros de emissão/tentativa.
- Métodos aceitos nos endpoints fiscais: `POST` e `OPTIONS`.
- `OPTIONS` aceito retorna HTTP `204`.

Códigos HTTP documentados:

| HTTP | Significado | Classificação local | Retry |
|---:|---|---|---|
| 200 | Sucesso | sucesso | Não aplicável. |
| 204 | `OPTIONS` aceito | sucesso técnico | Não aplicável. |
| 400 | JSON inválido, campo ausente, tipo inválido ou certificado inválido | normalmente definitivo ou erro de configuração/dados | Não automático; corrigir payload, cadastro ou certificado. |
| 401 | token ausente/inválido ou token não configurado | configuração/autenticação | Retry muito limitado apenas se houver suspeita de rotação; exige ação operacional. |
| 405 | método não permitido | erro de implementação | Não; corrigir cliente HTTP. |
| 502 | falha do serviço externo da NFS-e Nacional | temporário | Sim, com backoff; se ocorreu durante emissão, reconciliar antes de nova emissão. |
| 500 | erro interno inesperado | indeterminado | Retry limitado, com observabilidade e análise. |
| Timeout/conexão | sem HTTP | temporário com risco de sucesso remoto | Não repetir cegamente; consultar por DPS/chave antes. |

Envelope JSON padrão de sucesso:

```json
{
  "success": true,
  "requestId": "identificador-unico",
  "operation": "nomeDaOperacao",
  "data": {},
  "warnings": []
}
```

Envelope JSON padrão de erro:

```json
{
  "success": false,
  "requestId": "identificador-unico",
  "operation": "nomeDaOperacao",
  "data": null,
  "error": {
    "code": "CODIGO_PADRONIZADO",
    "message": "Mensagem segura",
    "details": {}
  }
}
```

O `requestId` também é retornado no header `X-Request-Id` e deve ser persistido para rastreabilidade.

### 8.2 Endpoints reais

| Endpoint | Método | Sucesso | Erro | Uso na integração | Observações de segurança |
|---|---|---|---|---|---|
| `/acoes/GeraDps.php` | POST | JSON padrão com `data` podendo conter `tipoAmbiente`, `versaoAplicativo`, `dataHoraProcessamento`, `idDps`, `chaveAcesso`, `nfseXmlGZipB64` | JSON padrão | Emissão da NFS-e a partir de pagamento confirmado | Recebe `cert` e `senhaCert`; nunca persistir esses campos. |
| `/acoes/CancelaNfse.php` | POST | JSON padrão com `operation: nfse.cancelar` | JSON padrão | Cancelamento fiscal quando nota já emitida e operação aprovar cancelamento | Envia `cert`, `senhaCert`, `idNota`, `cnpjEmitente`, `codigoMotivo`, `motivo`; ação admin explícita. |
| `/acoes/ConsultaDanfse.php` | POST | HTTP 200, `Content-Type: application/pdf`, header `X-Request-Id`, PDF binário, `Content-Disposition: inline` | JSON padrão | Obter DANFSE/PDF após emissão ou sob demanda | Envia `cert`, `senhaCert`, `idNota`; validar `Content-Type` e nunca tratar JSON de erro como PDF. |
| `/acoes/ConsultaDpsChave.php` | POST | JSON padrão com `operation: nfse.consultarDpsChave` | JSON padrão | Consulta de DPS por `chaveDps` | Envia `cert`, `senhaCert`, `chaveDps`; não consulta por `numDPS`. |
| `/acoes/ConsultaNfseChave.php` | POST | HTTP 200, `Content-Type: application/xml`, header `X-Request-Id`, XML textual | JSON padrão | Obter XML oficial por chave da NFS-e | Envia `cert`, `senhaCert` e `idNota` ou `chaveNfse`; validar XML e não logar conteúdo fiscal completo. |
| `/acoes/ConsultaNfseEventos.php` | POST | JSON padrão com `operation: nfse.consultarEventos` | JSON padrão | Auditoria/reconciliação de eventos da NFS-e | Envia `cert`, `senhaCert`, `idNota` ou `chaveNfse`, `tipoEvento` padrão `101101`, `numeroSequencial` padrão `1`; não assumir que os padrões cobrem eventos futuros. |

### 8.3 Contrato real de emissão — `POST /acoes/GeraDps.php`

Payload confirmado:

```json
{
  "cert": "PFX_BASE64",
  "senhaCert": "SENHA",
  "dadosNota": {
    "numDPS": "string",
    "dataNota": "AAAA-MM-DD",
    "localEmissao": "codigo IBGE com 7 dígitos",
    "prestador": {
      "CNPJ": "string",
      "IM": "string opcional",
      "optSimplesNacional": "número"
    },
    "tomador": {
      "CNPJ": "string",
      "nome": "string",
      "codMunicipio": "codigo IBGE",
      "CEP": "string",
      "logradouro": "string",
      "numero": "string",
      "bairro": "string",
      "fone": "string opcional",
      "email": "string opcional",
      "complemento": "string opcional"
    },
    "descServico": "string",
    "valorNota": "número"
  }
}
```

Campos obrigatórios confirmados: `dadosNota`, `dadosNota.prestador`, `dadosNota.tomador`, `dadosNota.numDPS`, `dadosNota.dataNota`, `dadosNota.localEmissao`, `dadosNota.prestador.CNPJ`, `dadosNota.prestador.optSimplesNacional`, `dadosNota.tomador.CNPJ`, `dadosNota.tomador.nome`, `dadosNota.tomador.codMunicipio`, `dadosNota.tomador.CEP`, `dadosNota.tomador.logradouro`, `dadosNota.tomador.numero`, `dadosNota.tomador.bairro`, `dadosNota.descServico`, `dadosNota.valorNota`.

Campos opcionais confirmados: `dadosNota.prestador.IM`, `dadosNota.tomador.fone`, `dadosNota.tomador.email`, `dadosNota.tomador.complemento`.

Configurações fiscais fixadas pela API: série DPS `900`, código de tributação nacional `170601`, tipo de emitente `prestador`, ISSQN como operação tributável, ISSQN não retido e percentual total aproximado do Simples `2`. Essas configurações não devem ser reenviadas pelo Disparador salvo se a API evoluir o contrato.

### 8.4 Matriz completa de integração

| Operação | Endpoint | Método | Payload | Response | Erro | Auth | Campo/referência de idempotência | Origem do dado no Disparador | Persistência necessária | Retry | Reconciliação |
|---|---|---|---|---|---|---|---|---|---|---|---|
| Emitir DPS/NFS-e | `/acoes/GeraDps.php` | POST | `cert`, `senhaCert`, `dadosNota` | JSON com `requestId`, `operation`, `data.idDps`, `data.chaveAcesso`, `data.nfseXmlGZipB64` quando disponíveis | JSON padrão | Bearer | Local: `COB_ID`, `NFE_IdempotencyKey`, `numDPS`; API sem idempotência nativa confirmada | Cobrança paga, cliente PJ com CNPJ/endereço, configurações fiscais do prestador em ambiente seguro | `NFE_RequestIdEmissao`, operation, numDPS, idDps, chaveAcesso, XML gzip/base64 ou XML extraído, status, tentativas, erro | Sim para 502/500 limitado; 400/401 não automático | Em timeout/perda de resposta sem chave consultável, marcar `reconciliacao_pendente` e revisão admin; não reenviar cegamente |
| Cancelar NFS-e | `/acoes/CancelaNfse.php` | POST | `cert`, `senhaCert`, `idNota`, `cnpjEmitente`, `codigoMotivo`, `motivo` | JSON padrão com `operation: nfse.cancelar` | JSON padrão | Bearer | Cancelamento local por `NFE_ID` e `NFE_ChaveAcesso`/idNota | Ação admin explícita com motivo fiscal | `NFE_RequestIdCancelamento`, operation, retorno sanitizado, status cancelamento | Sim para 502/conexão; 400 definitivo | Consultar eventos depois quando necessário |
| Consultar DANFSE PDF | `/acoes/ConsultaDanfse.php` | POST | `cert`, `senhaCert`, `idNota` | PDF binário com `Content-Type: application/pdf` | JSON padrão em erro | Bearer | Não cria nota | `NFE_ChaveAcesso`/idNota, não `numDPS` | PDF em storage privado, hash, `NFE_RequestIdConsulta` em sucesso/erro | Retry simples para 502/500 | Reconsultar após emissão confirmada |
| Consultar DPS por chave | `/acoes/ConsultaDpsChave.php` | POST | `cert`, `senhaCert`, `chaveDps` | JSON padrão com `operation: nfse.consultarDpsChave` | JSON padrão | Bearer | `chaveDps`; não aceita `numDPS` | `NFE_ChaveDps`, se existir | `NFE_RequestIdConsulta`, operation, response sanitizada, status remoto | Sim | Útil apenas quando `chaveDps` estiver disponível; não resolve timeout sem chave |
| Consultar NFS-e por chave/XML | `/acoes/ConsultaNfseChave.php` | POST | `cert`, `senhaCert`, `idNota` ou `chaveNfse` | XML textual com `Content-Type: application/xml` | JSON padrão em erro | Bearer | Chave da NFS-e (`chaveAcesso`/`chaveNfse`/`idNota`) | `NFE_ChaveAcesso` | XML em storage privado, hash, `NFE_RequestIdConsulta` | Retry simples | Recuperar XML ausente ou confirmar emissão com chave |
| Consultar eventos | `/acoes/ConsultaNfseEventos.php` | POST | `cert`, `senhaCert`, `idNota` ou `chaveNfse`, `tipoEvento` opcional padrão `101101`, `numeroSequencial` opcional padrão `1` | JSON padrão com `operation: nfse.consultarEventos` | JSON padrão | Bearer | Chave da NFS-e | `NFE_ChaveAcesso` | Eventos remotos sanitizados, `NFE_RequestIdConsulta` | Retry limitado | Auditoria/cancelamento/reconciliação após cancelamento ou dúvida operacional |

### 8.5 Separação obrigatória de identificadores

| Conceito | Origem | Uso permitido | Não presumir |
|---|---|---|---|
| `numDPS` | Gerado localmente e enviado em `dadosNota.numDPS` | Sequencial/local por cobrança, auditoria e prevenção local de duplicidade | Não consulta a API sozinho e não prova emissão remota. |
| `idDps` | Retornado em `data.idDps` pela emissão, quando houver resposta | Persistência e possível correlação com DPS | Não presumir equivalência com `chaveDps`. |
| `chaveDps` | Chave exigida por `ConsultaDpsChave.php` | Consulta de DPS por chave | Não derivar de `numDPS` ou `idDps` sem confirmação do contrato/código. |
| `chaveAcesso`/`chaveNfse`/`idNota` | Chave da NFS-e retornada/armazenada | Consultar PDF, XML, eventos e cancelar | Não substituir por `numDPS`. |
| `requestId` | JSON/header `X-Request-Id` por operação | Rastreabilidade com logs da API | Não há endpoint confirmado de reconciliação por `requestId`. |

## 9. Mapeamento de campos de `GeraDps.php` para o Disparador

| Campo API | Obrigatório | Origem definida no Disparador | Existe hoje? | Adequação necessária | Persistência/log |
|---|---:|---|---:|---|---|
| `cert` | Sim | Variável de ambiente/arquivo seguro operacional, por exemplo `NFSE_CERT_PFX_BASE64` ou path privado convertido em memória | Não | Definir operação segura de provisionamento | Nunca persistir em cobrança, histórico, payload salvo ou logs. |
| `senhaCert` | Sim | Variável de ambiente secreta, por exemplo `NFSE_CERT_PASSWORD` | Não | Definir segredo no ambiente da VPS/systemd | Nunca persistir ou logar. |
| `dadosNota.numDPS` | Sim | Gerado pelo Disparador a partir de `COB_ID` e ano/ambiente; persistido em `NFE_NumDps` | Não | Criar regra e índice único | Pode persistir; é referência fiscal. |
| `dadosNota.dataNota` | Sim | Data de emissão pretendida, preferencialmente data do processamento fiscal ou `COB_DataPagamento` conforme decisão fiscal | Derivável | Fechar regra de competência/data | Persistir. |
| `dadosNota.localEmissao` | Sim | Configuração do prestador no Disparador/env, código IBGE de 7 dígitos do município de emissão | Não | Definir `NFSE_LOCAL_EMISSAO_IBGE` | Persistir apenas valor não secreto se útil. |
| `dadosNota.prestador.CNPJ` | Sim | Configuração fiscal do prestador (`NFSE_PRESTADOR_CNPJ`) | Não no fluxo financeiro | Definir fonte operacional | Não é secreto, mas evitar duplicar por cobrança. |
| `dadosNota.prestador.IM` | Opcional | Configuração fiscal do prestador (`NFSE_PRESTADOR_IM`) | Não | Definir se aplicável | Não secreto. |
| `dadosNota.prestador.optSimplesNacional` | Sim | Configuração fiscal do prestador (`NFSE_OPT_SIMPLES_NACIONAL`) | Não | Definir valor numérico aceito pela API/fiscal | Não secreto. |
| `dadosNota.tomador.CNPJ` | Sim | `clientes.CLI_CPF_CNPJ`, somente quando cliente for PJ/CNPJ | Sim parcialmente | Primeira versão deve limitar emissão automática a PJ; CPF não é suportado no contrato informado | Persistir snapshot sanitizado. |
| `dadosNota.tomador.nome` | Sim | `CLI_RazaoSocial` para PJ; fallback controlado para `CLI_Nome` se fiscalmente aceito | Sim | Exigir razão social para PJ | Persistir snapshot. |
| `dadosNota.tomador.codMunicipio` | Sim | Novo campo fiscal de cliente/endereço, código IBGE | Não | Criar cadastro/validação antes da emissão | Persistir snapshot. |
| `dadosNota.tomador.CEP` | Sim | Novo campo de endereço fiscal do cliente | Não | Criar campo | Persistir snapshot. |
| `dadosNota.tomador.logradouro` | Sim | Novo campo de endereço fiscal do cliente | Não | Criar campo | Persistir snapshot. |
| `dadosNota.tomador.numero` | Sim | Novo campo de endereço fiscal do cliente | Não | Criar campo; validar `S/N` somente se aceito operacionalmente | Persistir snapshot. |
| `dadosNota.tomador.bairro` | Sim | Novo campo de endereço fiscal do cliente | Não | Criar campo | Persistir snapshot. |
| `dadosNota.tomador.fone` | Não | `clientes.CLI_Telefone` | Sim | Normalizar dígitos | Persistir snapshot se enviado. |
| `dadosNota.tomador.email` | Não | `clientes.CLI_Email` | Sim | Validar formato | Persistir snapshot se enviado. |
| `dadosNota.tomador.complemento` | Não | Novo campo de endereço fiscal | Não | Criar campo opcional | Persistir snapshot se enviado. |
| `dadosNota.descServico` | Sim | Descrição fiscal definida por plano/cobrança, por exemplo mensalidade do SaaS no ciclo contratado | Parcial | Padronizar texto fiscal aprovado | Persistir. |
| `dadosNota.valorNota` | Sim | `cobrancas.COB_Valor` ou valor efetivamente pago se houver ajustes futuros | Sim | Definir regra para juros/desconto/estorno parcial | Persistir. |

### 9.1 Emissão exige CNPJ do tomador

O contrato informado de `GeraDps.php` exige `dadosNota.tomador.CNPJ`; não há campo de CPF no payload confirmado. Portanto, o Disparador não deve presumir suporte a CPF.

Alternativas:

| Alternativa | Vantagens | Desvantagens | Decisão recomendada |
|---|---|---|---|
| Primeira versão somente para clientes PJ com CNPJ | Usa contrato atual sem modificar API; menor risco fiscal/técnico | Clientes PF ficam com pendência/manual ou sem emissão automática | Recomendada para primeira implementação. |
| Evoluir previamente a API RL2 NFS-e para aceitar CPF | Atende PF automaticamente | Exige alteração fora do escopo, validação fiscal e novo contrato | Registrar como evolução possível da API, não assumir agora. |

## 10. Campos exigidos pela API versus cadastro atual

| Campo | Obrigatório em `GeraDps.php` | Existe hoje | Origem atual | Adequação necessária |
|---|---:|---:|---|---|
| CNPJ do tomador | Sim | Parcial | `clientes.CLI_CPF_CNPJ` | Validar que é CNPJ e que `CLI_TipoPessoa` representa PJ. CPF não é suportado no contrato atual. |
| Razão Social/Nome do tomador | Sim (`nome`) | Sim | `CLI_RazaoSocial`/`CLI_Nome` | Exigir razão social para PJ ou regra fiscal de fallback. |
| Email | Opcional | Sim | `CLI_Email` | Validar formato; enviar se válido. |
| Telefone | Opcional | Sim | `CLI_Telefone` | Normalizar dígitos; enviar se válido. |
| CEP | Sim | Não identificado no cadastro atual | Ausente | Criar campo fiscal antes de emissão automática. |
| Cidade/Código IBGE | Sim (`codMunicipio`) | Não identificado | Ausente | Criar campo de código IBGE de 7 dígitos ou cadastro de endereço com seleção municipal. |
| UF | Não aparece diretamente no payload | Não identificado | Ausente | Pode ser necessário para UX/validação de município, mas não é enviado em `GeraDps.php`. |
| Endereço/logradouro | Sim | Não identificado | Ausente | Criar campo fiscal. |
| Número | Sim | Não identificado | Ausente | Criar campo fiscal obrigatório. |
| Complemento | Opcional | Não identificado | Ausente | Criar campo opcional. |
| Bairro | Sim | Não identificado | Ausente | Criar campo fiscal obrigatório. |
| `numDPS` | Sim | Não | Novo controle NFS-e | Gerar de forma única/determinística por cobrança e persistir. |
| `dataNota` | Sim | Derivável | `COB_DataPagamento`/data atual | Fechar regra fiscal. |
| `localEmissao` | Sim | Não | Configuração fiscal do prestador | Definir código IBGE do município de emissão. |
| CNPJ do prestador | Sim | Não no financeiro | Configuração fiscal segura | Definir em ambiente/configuração operacional. |
| IM do prestador | Opcional | Não | Configuração fiscal segura | Definir se houver. |
| Optante Simples | Sim | Não | Configuração fiscal segura | Definir valor numérico exigido. |
| Descrição do serviço | Sim | Parcial | Plano/ciclo/cobrança | Padronizar `descServico` fiscal. |
| Valor | Sim | Sim | `cobrancas.COB_Valor` | Definir tratamento de descontos/juros se surgirem. |
| Código de serviço/tributação | Fixado na API | Não enviado | API fixa `170601` e demais regras | Sem campo no payload atual; pendência apenas se operação precisar parametrizar no futuro. |
| Certificado PFX Base64 | Sim | Não | Segredo de ambiente/arquivo privado | Provisionar sem persistir por cobrança/log. |
| Senha do certificado | Sim | Não | Segredo de ambiente/systemd | Provisionar sem persistir por cobrança/log. |

Conclusão: a primeira versão implementável com o contrato atual deve atender somente clientes PJ com CNPJ e endereço fiscal completo. O cadastro atual contém identificação e contato básicos, mas faltam endereço fiscal, código IBGE do tomador, configurações do prestador, certificado, senha, município de emissão e controle de `numDPS`.

## 11. Classificação de erros e retry

| Classe | Exemplos | Status local | Retry | Ação |
|---|---|---|---|---|
| Temporário de rede | timeout, DNS, conexão recusada | `erro_temporario` | Sim, backoff exponencial com jitter | Reprocessar pelo Worker. |
| Rate limit | HTTP 429 | `erro_temporario` | Sim, respeitar `Retry-After` se existir | Aumentar `next_attempt_at`. |
| Servidor API | HTTP 500/502/503/504 | `erro_temporario` | Sim | Retry e observabilidade. |
| Processamento remoto assíncrono | response “processando” | `processando` ou `consulta_pendente` | Consulta posterior | Não emitir novamente. |
| Validação | HTTP 400/422 campos inválidos | `erro_definitivo` | Não automático | Corrigir cadastro/payload e reprocessar manualmente. |
| Cliente PF/CPF no contrato atual | payload exige `tomador.CNPJ` e não há CPF confirmado | `erro_definitivo_dados` ou `nao_elegivel_automatico` | Não | Primeira versão somente PJ ou evolução prévia da API para CPF. |
| CNPJ inválido | validação local/remota | `erro_definitivo` | Não | Solicitar correção. |
| Dados incompletos | endereço/código serviço ausente | `erro_definitivo` | Não | Pendência administrativa/cadastral. |
| Nota já emitida | conflito remoto/idempotência | `emitida` se identificável; senão `reconciliacao_pendente` | Consulta, não nova emissão | Persistir nota existente. |
| Persistência local | erro SQL após response | `reconciliacao_pendente` se possível | Consulta posterior | Não repetir emissão antes de consultar. |
| Autenticação | HTTP 401 | `erro_definitivo_config` após uma confirmação curta | Não automático | Corrigir Bearer token/API_AUTH_TOKEN. |
| Cancelamento fora de prazo | 4xx fiscal | `cancelamento_erro_definitivo` | Não | Tratar manualmente. |

Política sugerida: 8 tentativas máximas para temporários, atrasos aproximados de 1m, 5m, 15m, 30m, 1h, 3h, 6h, 12h, com jitter. Erros definitivos exigem ação admin e não devem consumir retry automático. Timeout em emissão é caso especial: marcar `reconciliacao_pendente`, não reenviar automaticamente `GeraDps.php`, consultar somente se houver `chaveDps` ou `chaveAcesso`/`chaveNfse`/`idNota` disponível e encaminhar para revisão administrativa quando não houver identificador consultável. A API atual não oferece consulta direta por `numDPS` nem idempotência nativa confirmada.

## 12. Fila de emissão

### 12.1 Reutilização do Worker atual

Recomenda-se adicionar um ciclo fiscal ao Worker daemon existente, mas com serviço separado, por exemplo `NfseQueueService`, chamado pelo runner de forma independente da fila de WhatsApp.

Regras:

- Não acoplar NFS-e a `fila_envio`, campanhas ou disparo manual.
- Usar tabela própria `nfse_emissoes` como fila transacional em MariaDB.
- Limitar lote fiscal por ciclo (`NFSE_WORKER_LIMITE`, decisão futura).
- Heartbeat do Worker deve incluir resumo fiscal separado.
- Falha fiscal não deve impedir envio de WhatsApp, e falha WhatsApp não deve impedir NFS-e.

### 12.2 Alternativas

| Alternativa | Decisão |
|---|---|
| Novo `QueueService` fiscal | Escolhido conceitualmente como `NfseQueueService`, usando MariaDB. |
| Reusar `WorkerService` diretamente | Rejeitado se misturar responsabilidades; aceitável só como orquestrador que chama serviço fiscal separado. |
| Worker separado | Rejeitado no início por operação extra. |
| Script manual | Rejeitado para fluxo primário; útil para backfill/reconciliação manual. |

## 13. Estados da NFS-e

```text
pendente
  -> processando
processando
  -> emitida
  -> consulta_pendente
  -> erro_temporario
  -> erro_definitivo
consulta_pendente
  -> emitida
  -> erro_temporario
  -> erro_definitivo
erro_temporario
  -> pendente/processando quando next_attempt_at vencer
  -> erro_definitivo quando max tentativas exceder
emitida
  -> cancelamento_pendente
cancelamento_pendente
  -> cancelada
  -> cancelamento_erro_temporario
  -> cancelamento_erro_definitivo
cancelamento_erro_temporario
  -> cancelamento_pendente
```

Estados finais: `emitida`, `cancelada`, `erro_definitivo`, `cancelamento_erro_definitivo`.

## 14. Disponibilização ao cliente

Área financeira deve mostrar, por fatura paga:

- status fiscal: pendente/processando/emitida/erro;
- número da nota;
- data de emissão;
- código de verificação;
- botões PDF/XML quando `emitida`;
- mensagem amigável quando `processando`;
- mensagem de pendência cadastral quando `erro_definitivo` por dados do cliente;
- nunca exibir tokens, payload bruto ou stack trace.

Autorização:

- cliente só acessa NFS-e cuja cobrança pertence ao seu `CLI_ID`;
- admin pode acessar todas;
- downloads devem passar por controller autenticado, não por URL pública direta para arquivo privado;
- registrar evento de download com usuário, IP e tipo de arquivo.

Estratégia PDF/XML recomendada: híbrida e privada. Persistir o XML retornado na emissão (`nfseXmlGZipB64` e/ou XML descompactado) em storage fora do document root; quando necessário, chamar `ConsultaNfseChave.php` com `idNota` ou `chaveNfse`, validar `Content-Type: application/xml` e conteúdo textual XML, armazenar em storage privado e registrar hash, sem logar o XML fiscal completo. Obter o PDF via `ConsultaDanfse.php` com `idNota`/chave da NFS-e, validar `Content-Type: application/pdf`, nunca aceitar JSON de erro como PDF, armazenar fora do document root, persistir caminho interno e hash recomendado. Downloads devem ser sempre autenticados pelo Disparador, sem links previsíveis/públicos, e cliente limitado às notas do próprio `CLI_ID`.

## 15. Painel administrativo

Funcionalidades recomendadas:

- listar emissões com filtros por status, cliente, cobrança, período, tentativa, código de erro;
- abrir cliente, cobrança e assinatura relacionados;
- consultar API RL2 para nota selecionada;
- reprocessar emissão em `erro_definitivo` após correção, com justificativa;
- reagendar retry de `erro_temporario`;
- cancelar NFS-e emitida, com motivo obrigatório;
- visualizar payload sanitizado e response sanitizada;
- baixar PDF/XML;
- ver timeline de `nfse_eventos`;
- painel de pendências: dados cadastrais incompletos, falhas temporárias há mais de X horas, reconciliações pendentes.

## 16. Segurança, LGPD e logs

- Tokens da API RL2 devem ficar em `.env`, nunca em banco/log/documento.
- Payloads devem ser sanitizados: sem Authorization, Bearer, API key, certificado/senha; limitar tamanho.
- PDF/XML contêm dados pessoais e fiscais: armazenar fora de diretório público ou servir via rota autenticada.
- URLs remotas temporárias não devem ser expostas se contiverem token; preferir proxy/download autenticado.
- Logs devem conter `NFE_ID`, `COB_ID`, status, HTTP, código de erro e mensagem truncada; evitar documento completo quando não necessário.
- Acesso admin deve exigir `Auth::admin`; cliente deve exigir `Auth::clienteAdmin`/cliente e checagem de propriedade.
- Retenção: alinhar prazo fiscal/legal; não apagar notas emitidas sem regra contábil.
- LGPD: minimizar dados exibidos em dashboard e registrar base operacional/fiscal para tratamento.

## 17. Estornos e cancelamentos

### 17.1 Estorno antes da emissão

Se cobrança for estornada/cancelada antes de `emitida`:

- `pendente`, `erro_temporario` ou `erro_definitivo`: marcar `cancelada_localmente`/`nao_emitir_estornada` e não chamar API.
- `processando` com lock expirado: reconciliar antes; se não emitida, encerrar localmente.

### 17.2 Estorno após emissão

Se a NFS-e já estiver emitida, não apagar nem alterar a nota local. Criar fluxo de cancelamento fiscal:

- status `cancelamento_pendente`;
- motivo obrigatório: estorno/reembolso/cancelamento comercial;
- cancelamento deve ser ação administrativa explícita, nunca automático apenas porque houve estorno;
- payload deve enviar `idNota`, `cnpjEmitente`, `codigoMotivo` e `motivo`, além de `cert` e `senhaCert`;
- códigos de motivo documentados: `1` desenquadramento do Simples, `2` enquadramento no Simples, `3` inclusão retroativa de imunidade/isenção, `4` exclusão retroativa de imunidade/isenção, `5` rejeição pelo tomador/intermediário responsável, `9` outros;
- não automatizar a escolha do motivo sem regra fiscal definida;
- persistir `NFE_RequestIdCancelamento`, retorno sanitizado, status/data;
- consultar eventos depois quando necessário, usando `tipoEvento` padrão `101101` e `numeroSequencial` padrão `1`, sem assumir que esses padrões cobrem eventos futuros;
- se prazo fiscal expirou ou API rejeitou, status `cancelamento_erro_definitivo` e tratamento manual/contábil.

### 17.3 Cobrança cancelada manualmente

- Sem nota emitida: cancelar fila fiscal local.
- Com nota emitida: exigir decisão admin explícita para cancelar NFS-e; não fazer automaticamente sem política fiscal.

## 18. Integração com e-mail

Fluxo recomendado:

- Enviar e-mail automático somente após status `emitida` e PDF/XML disponíveis ou link autenticado disponível.
- Preferir link autenticado ao invés de anexo, para reduzir exposição e tamanho; se a operação exigir anexo, anexar PDF/XML com cuidado e logs.
- Falha de e-mail não altera status fiscal; registrar `email_erro` em evento próprio e permitir reenvio admin.
- Cliente deve poder baixar pela área financeira independentemente do e-mail.

## 19. Reconciliação

Casos principais:

| Cenário | Tratamento |
|---|---|
| Timeout ou perda de resposta em `GeraDps.php` antes de persistir `idDps`/`chaveAcesso` | Risco alto: marcar `reconciliacao_pendente`, não reenviar automaticamente e encaminhar para revisão administrativa se não houver `chaveDps` ou chave da NFS-e consultável. |
| Sucesso remoto + falha local | Reconciliação consulta e persiste nota existente. |
| Nota emitida sem persistência de PDF/XML | Consultar XML com `ConsultaNfseChave.php` e PDF com `ConsultaDanfse.php`, desde que exista `chaveAcesso`/`idNota`. |
| Worker caiu em `processando` | Lock expira; próximo ciclo muda para reconciliação/consulta antes de nova emissão. |
| API retorna “já emitida” | Consultar nota existente e associar à cobrança somente se houver chave/identificador confiável retornado pela API. |
| Divergência cobrança paga sem NFS-e | Job de varredura lista cobranças `pago` sem `nfse_emissoes` e cria reserva idempotente. |

Rotina recomendada no Worker:

1. Processar pendentes de emissão.
2. Processar reconciliações pendentes.
3. Processar cancelamentos pendentes.
4. Varredura leve de cobranças pagas sem emissão, limitada por lote.

## 20. Observabilidade

- Logs JSON em `storage/logs/nfse-worker.log` e eventos em banco.
- Métricas no painel admin:
  - pendentes;
  - processando;
  - emitidas hoje/mês;
  - erros temporários;
  - erros definitivos;
  - reconciliações pendentes;
  - tentativas por emissão;
  - idade máxima da fila;
  - cancelamentos pendentes.
- Heartbeat do Worker deve incluir resumo fiscal separado do envio WhatsApp.
- Alertas operacionais manuais: lista admin destacada para erros definitivos e pendências antigas.

## 21. Transações

Regra principal: **nunca manter transação de banco aberta durante chamada HTTP à API RL2 NFS-e**.

### 21.1 Reserva

- Abrir transação curta no ponto de confirmação.
- Confirmar cobrança/cliente/assinatura.
- Inserir emissão se não existir.
- Commit.

### 21.2 Processamento

- Transação curta para reservar linha (`processando` + lock).
- Commit.
- Chamada HTTP fora de transação.
- Transação curta para persistir resposta/erro e evento.
- Commit.

### 21.3 Reconciliação

- Reservar linha com lock.
- Consultar API fora de transação.
- Persistir resultado em transação curta.

## 22. Decisões arquiteturais registradas

| Decisão | Escolha | Alternativas rejeitadas | Impacto |
|---|---|---|---|
| Onde emitir | Worker assíncrono após reserva local | Webhook/admin síncronos | Mais robustez, menor acoplamento, retry natural. |
| Fila | MariaDB em `nfse_emissoes` | Redis/RabbitMQ/Kafka/nova infra | Atende restrições e facilita transação com cobrança. |
| Idempotência | Único por `COB_ID` + `NFE_IdempotencyKey` | Somente provider event id | Cobre manual/admin e reconciliação, não só webhook. |
| Arquivos PDF/XML | Rota autenticada e storage privado | URL pública direta | Reduz vazamento fiscal/LGPD. |
| Erros definitivos | Pausar e exigir ação | Retry infinito | Evita loop e consumo indevido. |
| Cancelamento fiscal | Fluxo explícito após nota emitida | Apagar nota ou cancelar cobrança apenas | Preserva conformidade fiscal. |
| Contrato API | Não inventar endpoints | Assumir REST convencional | Evita implementação errada. |

## 23. Plano de implementação em etapas pequenas

1. **Completar contrato RL2 NFS-e no documento**: obter acesso ao repo/API e preencher seção 8/9 com endpoints reais. Testável por revisão documental.
2. **Adicionar configurações NFSE no `.env.example` e config**: URL base, auth, timeouts, limites. Reversível removendo constantes.
3. **Criar migrations fiscais**: `nfse_emissoes` e `nfse_eventos`, índices únicos. Testável com migration up/down.
4. **Criar models fiscais**: sem HTTP, apenas CRUD/locks/eventos. Testável com unidade/integrado local.
5. **Criar reserva idempotente**: chamar nos dois pontos de confirmação. Testável com webhook duplicado e admin manual.
6. **Criar `NfseClient`**: implementar contrato real RL2, sanitização e timeouts. Testável com mocks/responses reais.
7. **Criar `NfseQueueService`**: processar pendentes, retries, locks e transições. Testável com API mockada.
8. **Integrar Worker daemon**: chamar ciclo fiscal com limite próprio e métricas. Testável pelo Worker em modo controlado.
9. **Adicionar reconciliação**: timeout, processando expirado, cobrança paga sem emissão. Testável por cenários simulados.
10. **Área do cliente**: status e downloads autenticados. Testável com autorização cruzada.
11. **Painel admin**: lista, filtros, reprocessar, consultar, cancelar. Testável por permissões e fluxos.
12. **E-mail pós-emissão**: envio/reenvio sem alterar status fiscal. Testável com falha simulada.
13. **Backfill controlado**: script/admin para cobranças pagas antigas, se desejado. Testável em lote pequeno.
14. **Hardening**: logs, LGPD, limites, documentação operacional systemd. Reversível por flags.

## 24. Riscos

- Timeout ou perda de resposta durante `GeraDps.php` antes da persistência de `idDps`/`chaveAcesso` é risco alto, pois a API atual não oferece consulta direta por `numDPS` nem idempotência nativa confirmada.
- Cadastro atual parece insuficiente para endereço fiscal completo.
- Diferença entre valor cobrado e valor fiscal se houver juros/desconto/taxa no provedor.
- Estornos após emissão exigem decisão fiscal/contábil, não apenas técnica.
- Worker atual processa WhatsApp; adicionar ciclo fiscal requer isolamento para não degradar disparos.
- URLs remotas de PDF/XML podem expor dados se repassadas diretamente.
- Webhook Asaas hoje não usa transação explícita; reserva fiscal futura deve preservar consistência sem ampliar tempo de resposta.

## 25. Decisões pendentes

### 25.1 Pendências do Disparador

1. Definir provisionamento seguro do certificado PFX Base64: variável de ambiente, arquivo privado fora do webroot ou segredo injetado no systemd.
2. Definir provisionamento seguro da senha do certificado, sem persistência em banco/log/eventos.
3. Definir CNPJ do prestador, inscrição municipal, opção do Simples e município de emissão (`localEmissao` IBGE).
4. Criar/validar cadastro fiscal do tomador PJ: CNPJ, razão social, CEP, código IBGE, logradouro, número, bairro e complemento opcional.
5. Fechar regra de `dataNota`/competência: data do pagamento, data de processamento ou período da assinatura.
6. Fechar formato final de `numDPS`, tamanho aceito e índice único local.
7. Confirmar se existe equivalência ou derivação confiável entre `idDps` e `chaveDps`; até lá, não presumir equivalência.
8. Definir armazenamento híbrido de XML/PDF em storage privado e política de retenção.
9. Definir política para clientes PF enquanto a API exigir CNPJ: bloquear emissão automática, tratar manualmente ou exigir PJ.
10. Definir texto fiscal de `descServico` por plano/ciclo e tratamento de descontos/juros.
11. Definir política fiscal de cancelamento após estorno, incluindo prazo e aprovação administrativa.

### 25.2 Possíveis evoluções da API RL2 NFS-e

1. Suporte explícito a CPF do tomador, com contrato versionado e validação fiscal.
2. Chave de idempotência servidor-servidor documentada, por header ou campo, se a API vier a suportar.
3. Consulta por referência externa/`numDPS`, com semântica garantida para reconciliação pós-timeout.
4. Persistência interna de solicitações na API para impedir duplicidade em reenvios.
5. Endpoint de reconciliação por `requestId` ou chave idempotente.
6. Parametrização futura de código de tributação, série, retenção de ISSQN ou percentual do Simples se a operação fiscal exigir.

## 26. Melhorias futuras

- Pré-validação cadastral fiscal no onboarding/financeiro antes de permitir pagamento.
- Dashboard fiscal mensal com exportação contábil.
- Rotina de backfill com simulação e aprovação admin.
- Alertas por e-mail/Slack-equivalente já existente, caso exista ferramenta interna, para erros definitivos antigos.
- Parametrização de descrição/código fiscal por plano.
- Relatório LGPD de acessos a PDF/XML.

## 28. Implementação — Etapa 1

Esta seção registra a fundação local implementada para preparar a futura emissão de NFS-e sem chamar a API RL2 NFS-e, sem alterar webhook Asaas, sem integrar WorkerService e sem emitir/consultar/cancelar notas nesta etapa.

### 28.1 Migration criada

Migration versionada: `database/migrations/20260715_create_nfse_foundation.sql`.

Estruturas criadas/preparadas:

- `nfse_emissoes`: tabela local de controle de emissão fiscal, idempotência, identificadores fiscais, status, caminhos privados de PDF/XML, tentativas e erros sanitizados.
- `nfse_dps_sequencias`: tabela de sequência fiscal por prestador, ambiente e série, para reservar `numDPS` sem `SELECT MAX(...) + 1`.
- Campos fiscais em `clientes` com prefixo `CLI_NFSe_`, mantendo campos atuais compatíveis e sem obrigar clientes PF/PJ incompletos a preencherem dados fiscais para uso normal do sistema.

Rollback manual documentado na própria migration: remover `nfse_emissoes`, `nfse_dps_sequencias` e os campos `CLI_NFSe_*` adicionados em `clientes`, sempre após backup.

### 28.2 Modelo final e índices únicos

A tabela `nfse_emissoes` separa os conceitos documentados:

- `NFE_NumDps`: identificador sequencial/local enviado em `GeraDps.php`.
- `NFE_IdDps`: identificador retornado pela emissão, sem presumir equivalência com `chaveDps`.
- `NFE_ChaveDps`: chave exigida por `ConsultaDpsChave.php`, quando disponível.
- `NFE_ChaveAcesso`: chave da NFS-e usada como `idNota`/`chaveNfse` para PDF, XML, eventos e cancelamento.
- `NFE_RequestIdEmissao`, `NFE_RequestIdConsulta`, `NFE_RequestIdCancelamento`: rastreabilidade por tipo de operação.

Índices/restrições relevantes:

- `uk_nfse_cobranca (COB_ID)`: impede duas NFS-e independentes para a mesma cobrança quando `COB_ID` está preenchido.
- `uk_nfse_idempotency (NFE_IdempotencyKey)`: chave local estável baseada na cobrança.
- `uk_nfse_numdps_contexto (NFE_PrestadorCnpj, NFE_Ambiente, NFE_Serie, NFE_NumDps)`: impede repetição local de DPS dentro do mesmo prestador, ambiente e série, sem bloquear contextos fiscais distintos.
- `uk_nfse_dps_contexto (NDS_PrestadorCnpj, NDS_Ambiente, NDS_Serie)`: separa sequência por prestador, ambiente e série.

Status escolhido: `VARCHAR(40)`, seguindo a flexibilidade já usada em status do projeto e evitando migration complexa para cada novo estado operacional. Estados previstos: `pendente_dados`, `pendente`, `processando`, `reconciliacao_pendente`, `emitida`, `erro_temporario`, `erro_definitivo`, `cancelamento_pendente` e `cancelada`. A auditoria adicionou lista centralizada de transições permitidas no model para impedir retornos automáticos claramente inválidos, como `emitida` para `pendente` ou `cancelada` para reemissão.

### 28.3 Estratégia de idempotência local

A idempotência local foi preparada em `Models\NfseEmissao`:

- chave estável: `nfse:cobranca:{COB_ID}`;
- criação/localização por cobrança em `criarOuBuscarPorCobranca`;
- restrições únicas em banco para `COB_ID` e `NFE_IdempotencyKey`;
- tratamento de corrida: se dois processos tentarem inserir a mesma cobrança, a restrição única preserva uma emissão e o método volta a buscar o registro existente somente quando o erro de banco for duplicidade (`23000`/`1062`), propagando falhas reais.

Esta etapa não declara nem usa idempotência remota da API.

### 28.4 Estratégia de numDPS

A reserva de `numDPS` foi preparada em `Services\NfseDpsSequenciaService` usando tabela própria `nfse_dps_sequencias`:

- contexto por CNPJ do prestador, ambiente e série;
- transação local;
- `SELECT ... FOR UPDATE` para bloquear a linha da sequência;
- incremento persistente `NDS_ProximoNumero = NDS_ProximoNumero + 1`;
- série normalizada a partir de `NFSE_DPS_SERIE`, com padrão `900`, sem espalhar valor hardcoded pela aplicação; ambiente limitado a valores conhecidos e CNPJ do prestador sem máscara.

`numDPS` continua sendo identificador local/sequencial e não é tratado como chave de reconciliação remota.

### 28.5 Campos fiscais do cliente

Foram preparados campos `CLI_NFSe_*` para cliente PJ, reaproveitando dados existentes como fallback quando adequado:

- `CLI_NFSe_CNPJ`;
- `CLI_NFSe_RazaoSocial`;
- `CLI_NFSe_CEP`;
- `CLI_NFSe_Logradouro`;
- `CLI_NFSe_Numero`;
- `CLI_NFSe_Complemento`;
- `CLI_NFSe_Bairro`;
- `CLI_NFSe_Municipio`;
- `CLI_NFSe_UF`;
- `CLI_NFSe_CodigoIBGE`;
- `CLI_NFSe_Telefone`;
- `CLI_NFSe_Email`.

Clientes PF ou PJ incompletos permanecem aptos a usar o sistema, mas não aptos para emissão fiscal automática na primeira versão.

### 28.6 Validação de aptidão fiscal

`Services\NfseAptidaoFiscalService` valida localmente, sem rede:

- cliente existente;
- tipo PJ/CNPJ;
- CNPJ válido;
- razão social fiscal;
- CEP;
- logradouro;
- número;
- bairro;
- código IBGE com 7 dígitos.

O retorno é estruturado com `apto`, `tipo_bloqueio`, `campos_faltantes` e `mensagem`, sem expor segredos ou conteúdo fiscal sensível. PF permanece como não apto/não suportado nesta etapa, não como erro técnico de emissão.

### 28.7 Configuração segura

`config/config.php` passou a referenciar variáveis de ambiente para a futura integração:

- `NFSE_API_BASE_URL`;
- `NFSE_API_AUTH_TOKEN`;
- `NFSE_PRESTADOR_CNPJ`;
- `NFSE_PRESTADOR_IM`;
- `NFSE_PRESTADOR_OP_SIMPLES`;
- `NFSE_LOCAL_EMISSAO_IBGE`;
- `NFSE_DPS_SERIE`;
- `NFSE_AMBIENTE`;
- `NFSE_CERT_PATH`;
- `NFSE_CERT_PASSWORD`;
- `NFSE_CONNECT_TIMEOUT`;
- `NFSE_REQUEST_TIMEOUT`.

Nenhum valor secreto real foi incluído no código. O certificado deve permanecer em arquivo protegido fora do document root e poderá ser convertido para Base64 apenas em memória em etapa futura. `API_AUTH_TOKEN`, certificado PFX/Base64, senha do certificado e Authorization não são persistidos em banco. A auditoria reforçou a sanitização de mensagens para `Authorization`, `Bearer`, `senhaCert`, `CERT_PASSWORD`, `PFX`, `base64`, senhas e caminhos sensíveis.

### 28.8 Storage privado

`.gitignore` ignora `/storage/nfse/` para futuros XML/PDF fiscais. A estratégia permanece: arquivos fiscais fora do document root, download autenticado pelo Disparador e sem links públicos/previsíveis. Esta etapa não cria XML/PDF falso e não grava documento fiscal.

### 28.9 Limitações da primeira versão

- Não chama a API RL2 NFS-e.
- Não emite NFS-e.
- Não consulta PDF/XML/eventos.
- Não cancela NFS-e.
- Não integra webhook Asaas nem confirmação manual.
- Não adiciona ciclo no WorkerService.
- Não implementa retry operacional real.
- Não cria interface administrativa completa nem download do cliente.
- Não envia e-mail fiscal.
- Não oferece suporte a CPF para emissão automática.
- Não armazena certificado, Base64, senha ou token no banco.

## 27. Confirmações deste levantamento

- Nenhum código PHP foi alterado.
- Nenhuma migration foi criada.
- Nenhum banco foi alterado.
- Nenhum webhook foi alterado.
- Nenhuma chamada real à API RL2 NFS-e foi implementada.
- Único arquivo criado neste trabalho: `docs/NFSE_LEVANTAMENTO_TECNICO.md`.

## 29. Implementação — Etapa 2

A Etapa 2 implementa a comunicação HTTP real com a API RL2 NFS-e para emissão manual controlada por administrador, sem integração automática com pagamento, webhook Asaas, Worker, retry automático, download do cliente, e-mail, cancelamento automático ou suporte a CPF.

Componentes criados:

- `Services\NfseApiClient`: client HTTP cURL para os endpoints reais `/acoes/GeraDps.php`, `/acoes/ConsultaDanfse.php`, `/acoes/ConsultaNfseChave.php`, `/acoes/ConsultaDpsChave.php`, `/acoes/ConsultaNfseEventos.php` e `/acoes/CancelaNfse.php`.
- `Services\NfsePayloadBuilder`: montagem do payload de emissão com `cert`, `senhaCert` e `dadosNota`, usando somente dados reais e configuração fiscal local.
- `Services\NfseApiResponseMapper`: normalização de respostas JSON/PDF/XML e classificação de erros definitivos, temporários e incertos.
- `Services\NfseEmissionService`: orquestração da emissão manual, com validação fiscal, reserva de `numDPS` somente quando apto, chamada HTTP fora de transação longa e persistência local do resultado.
- `Services\NfseSanitizer`: sanitização centralizada de mensagens, arrays e logs.
- `Controllers\NfseController` e `Views\nfse\index`: tela administrativa simples para listar emissões, iniciar emissão manual e consultar PDF por ação explícita.

Decisões preservadas:

- não presumir idempotência remota;
- não usar `numDPS` como chave de reconciliação remota;
- não reenviar automaticamente emissão incerta;
- não reservar `numDPS` para cliente fiscalmente inapto;
- manter `cert` e `senhaCert` somente em memória;
- salvar XML/PDF somente em storage privado fora de `public/`;
- nunca persistir token, certificado, senha, payload completo, XML integral em logs ou PDF em logs.

Não foi criada migration complementar nesta etapa: o schema da fundação já contém os campos necessários para requestIds, identificadores fiscais, status, retorno sanitizado e paths/hashes privados de XML/PDF.

## Implementação — Parametrização fiscal preparada no Disparador

O Disparador agora possui as variáveis `NFSE_CODIGO_TRIBUTACAO_NACIONAL` e `NFSE_DESCRICAO_SERVICO` para preparar a parametrização do código tributário e da descrição do serviço por ambiente. A API RL2 NFS-e ainda não foi alterada nesta etapa; portanto, o contrato HTTP permanece compatível e o `NfsePayloadBuilder` mantém um TODO explícito para enviar `codigoTributacaoNacional` somente quando a API suportar o campo.

A descrição usada em `descServico` deixa de ser montada com texto fixo e passa a vir de `NfseConfigService::descricaoServico()`, sem concatenação automática do nome da plataforma. Se código tributário ou descrição estiverem ausentes, a emissão manual é bloqueada antes da reserva de DPS e antes da chamada HTTP. O painel administrativo mostra aviso de configuração incompleta e prévia dos valores configurados para conferência. As ocorrências anteriores de `170601` neste documento permanecem como registro histórico do contrato observado na API antes da parametrização.
