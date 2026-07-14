# Levantamento técnico — Worker contínuo

## 1. Arquitetura atual

O projeto é uma aplicação PHP MVC simples, com front controller em `public/index.php`/`index.php`, controllers em `app/Controllers`, models em `app/Models`, serviços em `app/Services` e scripts CLI na raiz. A persistência usa `Core\Database` e SQL direto via PDO.

O processamento de envios está distribuído em três caminhos principais:

1. **Disparo manual síncrono/AJAX** em `app/Controllers/DisparoController.php`, que valida template/conta, normaliza números e variáveis, chama `Services\MetaService::enviarTemplate()`, registra em `disparos`, `conversas`/`conversa_mensagens` e incrementa consumo.
2. **Disparo manual em lote** com fila própria em `disparo_manual_lotes` e `disparo_manual_itens`, criado pelo controller e processado por `Services\DisparoManualQueueService`, via endpoints AJAX e também por `worker.php`.
3. **Campanhas agendadas** em `campanhas` e `fila_envio`, criadas por `CampanhaController::criar()` e processadas por `worker.php` quando `CAM_Status = 'agendada'` e `CAM_DataAgendamento <= NOW()`.

O arquivo `worker.php` já é um worker CLI, bloqueado para HTTP, com lock local via `storage/worker.lock`, logs em `storage/logs/worker.log` e `storage/logs/worker-error.log`, limites fixos por execução e processamento sequencial de disparos manuais pendentes e campanhas. A documentação de operação atual recomenda execução por `php worker.php` e instalação como serviço systemd em `docs/deploy-worker-vps.md`.

A Meta Cloud API é centralizada principalmente em `app/Services/MetaService.php`, com métodos de teste de conexão, templates e envio de mensagens. O envio de template monta payload para `/{phone_number_id}/messages` usando `MTA_UrlBase`, `MTA_PhoneNumberId`, `MTA_Token`, `TMP_Nome`, `TMP_Idioma`, variáveis do body e, quando aplicável, mídia de header.

O webhook da Meta fica em `public/webhook/meta.php`; ele valida `GET` de assinatura por `MTA_WebhookVerifyToken`, valida `POST` por `X-Hub-Signature-256` usando `META_APP_SECRET`, consome mensagens recebidas, envia auto resposta quando configurada e atualiza status por `MSG_MetaMessageId`, `DSP_MessageId`, `FIL_MessageId` e `DMI_MessageId`.

## 2. Fluxo atual do disparo manual

### 2.1 Envio manual direto

Arquivos/métodos principais:

- `app/Controllers/DisparoController.php`: `enviar()`, `enviarAjax()`, `processarEnvioManualDestino()`, `pausaEntreEnviosManual()`, `statusAjax()`.
- `app/Services/MetaService.php`: `enviarTemplate()`.
- `app/Models/Disparo.php`: `salvar()`, `buscarPorMessageIds()`.
- `app/Models/ConsumoMensal.php`: `registrarMensagem()`.
- `app/Services/ControlePlanoService.php`: `registrarUso()`.
- `app/Models/Conversa.php`: `buscarOuCriar()` e `salvarMensagem()`.

Fluxo resumido:

1. O usuário seleciona conta Meta (`MTA_ID`) e template (`TMP_ID`) na tela de disparo.
2. `DisparoController` busca template aprovado com `TemplateMeta::buscarAprovadoParaEnvioPorCliente()` e confere se o `MTA_ID` do template corresponde ao informado.
3. O número é normalizado para dígitos e prefixo `55`; no envio em massa direto, `array_unique()` remove números repetidos dentro da requisição.
4. As variáveis do template são extraídas de `TMP_Componentes` e normalizadas para os placeholders esperados.
5. `MetaService::enviarTemplate()` chama a Cloud API.
6. Se a resposta contém `messages[0].id`, o status interno inicial passa para `aguardando_confirmacao`; caso contrário fica `erro`.
7. Todo envio direto registra uma linha em `disparos` por `Disparo::salvar()`.
8. Todo envio direto cria/atualiza conversa e salva mensagem enviada em `conversa_mensagens`.
9. Em sucesso aceito pela Meta, incrementa `consumo_mensal.CMS_Mensagens` e dispara `ControlePlanoService::registrarUso()` para eventual excedente.
10. A atualização final para `enviado`, `entregue`, `lido` ou `erro` depende do webhook Meta.

### 2.2 Envio manual em lote

Arquivos/métodos principais:

- `app/Controllers/DisparoController.php`: pontos AJAX de criação/processamento/status de lote; usa `DisparoManual` e `DisparoManualQueueService`.
- `app/Models/DisparoManual.php`: `criarLote()`, `adicionarItem()`, `listarItensCliente()`, `recalcularLote()`.
- `app/Services/DisparoManualQueueService.php`: `processarLote()`, `processarPendentes()`, `buscarItensPendentes()`, `reservarItem()`, `enviarItem()`, `registrarSucesso()`, `registrarErro()`.
- `worker.php`: instancia `DisparoManualQueueService` e chama `processarPendentes($limiteDisparoManualPorExecucao, 'cron')`.

Fluxo resumido:

1. O lote é criado em `disparo_manual_lotes` com `DML_Status = 'pendente'`, totais e referência a cliente, conta Meta e template.
2. Cada destinatário vira item em `disparo_manual_itens` com `DMI_Status = 'pendente'`, `DMI_Numero` e `DMI_VariaveisJson`.
3. O processamento pode ocorrer por AJAX para um lote específico (`processarLote`) ou por execução global/cron (`processarPendentes`).
4. Antes de enviar, `DisparoManualQueueService::reservarItem()` troca atomicamente `DMI_Status` de `pendente` para `processando` com condição `DMI_Status = 'pendente'`.
5. `enviarItem()` reutiliza `MetaService::enviarTemplate()`, com cache de instâncias por `MTA_ID:CLI_ID` e suporte a header de mídia salvo no lote.
6. Em sucesso, `registrarSucesso()` grava `DMI_Status = 'aguardando_confirmacao'`, `DMI_MessageId`, `DMI_Retorno`, `DMI_DataEnvio`, cria linha em `disparos`, incrementa consumo/plano e salva mensagem enviada em conversa.
7. Em falha, `registrarErro()` grava `DMI_Status = 'erro'`, `DMI_Erro` e `DMI_Retorno`.
8. O lote é recalculado com totais e muda para `concluido` quando não restarem itens `pendente` ou `processando`.

## 3. Fluxo atual das campanhas agendadas

Arquivos/métodos principais:

- `app/Controllers/CampanhaController.php`: `criar()`, `detalhes()`, `cancelar()`, `reagendar()`, `preview()`.
- `app/Models/Campanha.php`: `salvar()`, `buscar()`, `buscarPorCliente()`, `listarFilaPorCliente()`, `cancelar()`, `reagendar()`, `resetarFilaPorCliente()`.
- `app/Models/FilaEnvio.php`: `adicionar()`.
- `app/Models/CampanhaVariavel.php`: `salvar()`, `listarPorCampanha()`.
- `worker.php`: processamento efetivo.

Fluxo resumido:

1. `CampanhaController::criar()` valida CSRF, cliente, lista e template aprovado.
2. `Campanha::salvar()` cria registro em `campanhas` com `CAM_Status = 'agendada'`, `CAM_DataAgendamento`, `CAM_TotalContatos = 0`, `CAM_TotalEnviados = 0` e `CAM_TotalErros = 0`.
3. Variáveis são persistidas em `campanha_variaveis` (`CPV_Variavel`, `CPV_Campo`).
4. A lista de contatos é expandida via `ListaContatoItem::listarIdsDaLista()` e cada contato gera uma linha em `fila_envio` por `FilaEnvio::adicionar()`.
5. `Campanha::atualizarTotalContatos()` grava o total na campanha.
6. `worker.php` busca campanhas `agendada` com `CAM_DataAgendamento <= NOW()` e muda para `processando`.
7. Em seguida busca campanhas `processando`, carrega o template em `templates_meta`, as variáveis em `campanha_variaveis` e até `$limitePorExecucao = 50` itens `fila_envio` com `FIL_Status = 'pendente'`.
8. Para cada item, atualiza `FIL_Status = 'processando'` e incrementa `FIL_Tentativas` antes de chamar a Meta.
9. Em sucesso, grava `FIL_Status = 'aguardando_confirmacao'`, `FIL_DataEnvio`, `FIL_MessageId`, `FIL_Retorno`, incrementa `CAM_TotalEnviados`, registra consumo/plano e salva mensagem enviada na conversa.
10. Em erro, `registrarErro()` grava `FIL_Status = 'erro'`, `FIL_Erro`, `FIL_Retorno` e incrementa `CAM_TotalErros`.
11. `finalizarSeConcluida()` encerra a campanha como `finalizada` quando não existem itens `pendente` ou `processando`.
12. O webhook posteriormente troca `fila_envio.FIL_Status` por `enviado`, `entregue`, `lido` ou `erro` via `FIL_MessageId`.

## 4. Tabelas e estados envolvidos

### 4.1 Campanhas

Tabela `campanhas`:

- Identificação: `CAM_ID`, `CLI_ID`, `TMP_ID`, `LST_ID`.
- Mídia de header: `CAM_HeaderMidiaTipo`, `CAM_HeaderMidiaId`, `CAM_HeaderMidiaNome`, `CAM_HeaderMidiaMime`, `CAM_HeaderMidiaTamanho`.
- Dados funcionais: `CAM_Nome`, `CAM_Descricao`, `CAM_DataAgendamento`, `CAM_DataCadastro`.
- Totais: `CAM_TotalContatos`, `CAM_TotalEnviados`, `CAM_TotalErros`.
- Estado: `CAM_Status`.

Estados observados de `CAM_Status`:

- `agendada`: criada ou reagendada e elegível para processamento quando `CAM_DataAgendamento <= NOW()`.
- `processando`: assumida pelo worker.
- `finalizada`: sem itens pendentes/processando.
- `cancelada`: cancelada pelo usuário ou por ausência de template no worker.
- `rascunho`: aceito em `Campanha::cancelar()`, embora não apareça como status inicial atual.

### 4.2 Fila de campanhas

Tabela `fila_envio`:

- Identificação: `FIL_ID`, `CAM_ID`, `CON_ID`.
- Estado: `FIL_Status`.
- Tentativas/erro: `FIL_Tentativas`, `FIL_Erro`.
- Rastreamento Meta: `FIL_MessageId`, `FIL_Retorno`.
- Data: `FIL_DataEnvio`.

Estados observados de `FIL_Status`:

- `pendente`: aguardando worker.
- `processando`: item reservado/em envio.
- `aguardando_confirmacao`: aceito pela Meta, aguardando webhook.
- `enviado`: mapeado do webhook Meta `sent`.
- `entregue`: mapeado do webhook Meta `delivered`.
- `lido`: mapeado do webhook Meta `read`.
- `erro`: falha local ou webhook Meta `failed`.

### 4.3 Disparo manual em lote

Tabela `disparo_manual_lotes` criada em `database/migrations/20260624_create_disparo_manual_queue.sql` e expandida em `database/migrations/20260630_add_templates_media_headers.sql`:

- Identificação: `DML_ID`, `CLI_ID`, `MTA_ID`, `TMP_ID`.
- Mídia de header: `DML_HeaderMidiaTipo`, `DML_HeaderMidiaId`, `DML_HeaderMidiaNome`, `DML_HeaderMidiaMime`, `DML_HeaderMidiaTamanho`.
- Totais: `DML_Total`, `DML_TotalEnviados`, `DML_TotalErros`.
- Estado: `DML_Status`.
- Datas: `DML_DataCadastro`, `DML_DataAtualizacao`, `DML_DataConclusao`.

Estados observados de `DML_Status`:

- `pendente`.
- `processando`.
- `concluido`.
- `concluido_com_erros` aparece na UI/filtros, mas o recálculo atual grava apenas `processando` ou `concluido`.
- `erro` aparece na UI/filtros, mas não foi encontrado como status gravado para lote no fluxo principal.

Tabela `disparo_manual_itens`:

- Identificação: `DMI_ID`, `DML_ID`, `CLI_ID`.
- Destino/dados: `DMI_Numero`, `DMI_VariaveisJson`.
- Estado: `DMI_Status`.
- Rastreamento Meta: `DMI_MessageId`, `DMI_Retorno`, `DMI_Erro`.
- Datas: `DMI_DataCadastro`, `DMI_DataAtualizacao`, `DMI_DataEnvio`.

Estados observados de `DMI_Status`: `pendente`, `processando`, `aguardando_confirmacao`, `enviado`, `entregue`, `lido`, `erro` e aliases legados/externos tratados na UI (`sent`, `delivered`, `read`, `failed`).

### 4.4 Registro geral de disparos e conversas

Tabela `disparos`:

- `CLI_ID`, `MTA_ID`, `TMP_ID`, `DSP_Numero`, `DSP_Template`, `DSP_Variaveis`, `DSP_MessageId`, `DSP_Status`, `DSP_Retorno`.
- Recebe linhas de disparo direto e de lote manual; campanhas atualmente registram conversa/consumo, mas no trecho atual de `worker.php` não chamam `Disparo::salvar()` para cada item de campanha.

Tabelas `conversas` e `conversa_mensagens`:

- Usadas para centralizar mensagens enviadas/recebidas.
- `conversa_mensagens.MSG_MetaMessageId` é chave de correlação do webhook para atualizar `MSG_Status` e `MSG_Retorno`.

### 4.5 Meta, templates e consumo

Tabela `meta_contas`:

- Campos usados no envio/webhook: `MTA_ID`, `CLI_ID`, `MTA_UrlBase`, `MTA_PhoneNumberId`, `MTA_WabaId`, `MTA_Token`, `MTA_WebhookVerifyToken`, `MTA_Ativo`, `MTA_Status`.
- Migrações recentes expandem status para `autorizada`, `conectado`, `requer_acao`, `erro`, `desconectado` e adicionam metadados operacionais.

Tabela `templates_meta`:

- Campos usados no envio: `TMP_ID`, `MTA_ID`, `TMP_Nome`, `TMP_Idioma`, `TMP_Componentes`, `TMP_Status`, campos de header `TMP_HeaderTipo`, `TMP_HeaderMidiaModo`, `TMP_HeaderMidiaUrlExemplo`, `TMP_HeaderMidiaHandle`, `TMP_HeaderDocumentoNome`.

Tabela `consumo_mensal`:

- `CLI_ID`, `CMS_AnoMes`, `CMS_Mensagens` e, quando disponíveis, `CMS_PLA_ID`, `CMS_LimiteMensagens`, `CMS_ValorMensagemExcedente`.
- `ConsumoMensal::registrarMensagem()` incrementa no aceite do envio pela Meta, não no status entregue/lido.

## 5. Integrações com a Meta Cloud API

`MetaService::enviarTemplate()` monta requisição `POST` para:

```text
{MTA_UrlBase}/{MTA_PhoneNumberId}/messages
```

Payload principal:

- `messaging_product = whatsapp`.
- `to` normalizado para dígitos.
- `type = template`.
- `template.name = TMP_Nome`.
- `template.language.code = TMP_Idioma`.
- `template.components` com header de mídia e parâmetros de body quando existirem.

O método retorna o JSON decodificado da Meta e acrescenta `http_code`/`payload` em cenários de erro/curl/resposta inválida. Chamadores consideram sucesso quando existe `messages[0].id`.

Limitação/rate limit:

- `config/config.php` define `WHATSAPP_ENVIOS_POR_SEGUNDO = 5` e `WHATSAPP_PAUSA_RATE_LIMIT_SEGUNDOS = 5`.
- `worker.php` e `DisparoManualQueueService` possuem funções equivalentes para detectar HTTP 429 ou códigos Meta `4`, `17`, `32`, `613`, além de mensagens com `rate limit`/`too many`.
- Ao detectar rate limit, o código dorme por poucos segundos, mas o item que recebeu erro pode ser marcado como `erro` em vez de retornar para retry controlado.

## 6. Funcionamento do webhook

`public/webhook/meta.php` possui dois caminhos:

1. **GET de verificação**: recebe `hub.mode`, `hub.verify_token`, `hub.challenge`; procura `meta_contas.MTA_WebhookVerifyToken` ativo e responde o challenge.
2. **POST de evento**: valida assinatura `X-Hub-Signature-256` com `META_APP_SECRET`; registra log; processa `entry[].changes[].value`.

Para mensagens recebidas:

- Localiza `meta_contas` por `metadata.phone_number_id`.
- Cria/obtém conversa por `Conversa::buscarOuCriar()`.
- Salva mensagem recebida em `conversa_mensagens` com `MSG_Status = 'recebida'`.
- Pode enviar auto resposta se `MTA_AutoRespostaAtiva = 'S'`, texto configurado e intervalo respeitado por `conversas.CVS_DataUltimaAutoResposta`.

Para status de mensagens enviadas:

- Lê `statuses[].id` como message id externo e `statuses[].status`.
- Mapeia `sent => enviado`, `delivered => entregue`, `read => lido`, `failed => erro`.
- Atualiza `conversa_mensagens` por `MSG_MetaMessageId`.
- Atualiza `disparos` por `DSP_MessageId`.
- Atualiza `fila_envio` por `FIL_MessageId`, incluindo `FIL_Erro` quando houver erro Meta.
- Atualiza `disparo_manual_itens` por `DMI_MessageId`, incluindo `DMI_Erro` quando houver erro Meta.

## 7. Controle de consumo, trial e bloqueios

O controle de uso atual é acionado quando a Meta aceita a mensagem (`messages[0].id`), antes do webhook confirmar entrega. Isso ocorre nos fluxos de disparo direto, lote manual e campanhas.

Componentes:

- `ConsumoMensal::registrarMensagem($cliId)`: busca/cria registro do mês (`CMS_AnoMes = date('Ym')`) e incrementa `CMS_Mensagens`.
- `ControlePlanoService::registrarUso($cliId)`: consulta cliente/plano, compara `CMS_Mensagens` contra `CMS_LimiteMensagens`/`PLA_LimiteMensagens` e registra excedente em `ExcedenteMensal` quando ultrapassa o limite.
- `Core\Auth::clienteLiberado()`: libera cliente pago/ativo, cliente pendente/ativo em tolerância financeira ou avaliação ativa.
- `Core\Auth::dadosAvaliacaoCliente()`: calcula avaliação/trial para clientes com `CLI_StatusPagamento = 'pendente'` e `CLI_StatusCadastro = 'ativo'`.
- `Core\Auth::validarBloqueioFinanceiro()`: bloqueia rotas fora da área financeira quando cliente não está liberado.
- Scripts financeiros `processar_vencimentos.php` e `gerar_cobrancas_recorrentes.php` usam `FinanceiroRecorrenciaService` para vencimentos/recorrência, mas não processam mensagens.

Ponto crítico para Worker contínuo: `worker.php` roda em CLI e não passa por `Auth::validarBloqueioFinanceiro()`. O processamento de campanhas/lotes deve validar explicitamente se o cliente continua liberado antes de enviar, caso contrário um lote pendente pode continuar disparando após bloqueio financeiro/trial.

## 8. Componentes reutilizáveis

Componentes diretamente reutilizáveis:

- `Services\MetaService::enviarTemplate()` para envio à Meta.
- `Services\DisparoManualQueueService` para reserva atômica e processamento de itens de `disparo_manual_itens`.
- `Models\DisparoManual` para criação/listagem/recálculo de lotes.
- `Models\Campanha`, `Models\CampanhaVariavel`, `Models\FilaEnvio` para estrutura de campanhas.
- `Models\Disparo` para histórico por message id.
- `Models\Conversa` para persistir mensagens enviadas/recebidas.
- `Models\ConsumoMensal` e `Services\ControlePlanoService` para consumo/excedente.
- Funções de `worker.php` para lock, logging, rate limit e finalização de campanha, embora hoje estejam como funções globais e não como serviço testável.
- `public/webhook/meta.php` para atualização assíncrona de status por message id.
- Constantes `WHATSAPP_ENVIOS_POR_SEGUNDO`, `WHATSAPP_PAUSA_RATE_LIMIT_SEGUNDOS`, `FINANCEIRO_DIAS_TOLERANCIA_VENCIMENTO`.

Componentes que exigem adaptação antes de reutilização ampla:

- Lógica de campanha em `worker.php` está procedural e duplicada em relação a padrões do `DisparoManualQueueService`.
- `worker.php` usa `new MetaService($template['MTA_ID'])` sem `CLI_ID`, diferente do lote manual que restringe por cliente.
- `registrarErro()`, `aplicarLimiteEnvio()` e `extrairErroMetaWorker()` existem em duplicidade no worker e no serviço de lote manual.

## 9. Riscos técnicos encontrados

1. **Campanhas sem reserva atômica forte por item**: `worker.php` busca itens `pendente` e depois faz `UPDATE fila_envio SET FIL_Status = 'processando' WHERE FIL_ID = ?` sem condicionar `FIL_Status = 'pendente'` nem checar `rowCount()`. O lock por arquivo reduz concorrência na mesma cópia, mas não protege múltiplos servidores/cópias/release paths.
2. **Campanhas sem lock compartilhado no banco**: `storage/worker.lock` é local. A própria documentação alerta que múltiplas VPS/cópias exigem lock compartilhado.
3. **Itens presos em `processando`**: se o processo morrer após marcar `fila_envio` ou `disparo_manual_itens` como `processando`, não há rotina de expiração/requeue desses estados.
4. **Sem retry real para erros temporários**: `FIL_Tentativas` é incrementado, mas erros são marcados como finais (`erro`). No lote manual não há contador de tentativas em `disparo_manual_itens`.
5. **Rate limit tratado como erro do item**: a pausa existe, mas o item que recebeu rate limit pode ser contabilizado como erro, sem backoff persistente.
6. **Bloqueio financeiro/trial não aplicado no worker CLI**: campanhas e lotes pendentes podem continuar se o cliente for bloqueado após a criação da fila.
7. **Risco de duplicidade entre disparo direto e fila**: não há chave única por cliente/campanha/lote/número/template nem idempotency key interna. O histórico `disparos` aceita múltiplas linhas para o mesmo destino/template.
8. **Reagendamento reseta fila potencialmente enviada**: `Campanha::resetarFilaPorCliente()` volta todos os itens para `pendente`, zera tentativas, erro e data de envio; se usado após envio parcial, pode reenviar contatos já aceitos pela Meta, pois não preserva `FIL_MessageId`/status final.
9. **Contadores podem divergir do estado real**: `CAM_TotalEnviados` incrementa no aceite inicial, e webhook pode depois marcar item como `erro`; não há recálculo de totais da campanha após webhook.
10. **`DisparoManual::recalcularLote()` não diferencia concluído com erro**: mesmo com erros, se não houver pendentes, grava `concluido`; a UI prevê `concluido_com_erros`.
11. **Campanhas não registram `disparos` no worker atual**: status de campanhas é rastreado por `fila_envio` e conversas, mas não aparece no histórico geral de `disparos` como os disparos diretos/lote manual.
12. **Webhook sem deduplicação explícita de mensagens recebidas/status**: atualizações por status são idempotentes por `MessageId`, mas mensagens recebidas podem gerar duplicidade se a Meta reenviar o mesmo evento e `Conversa::salvarMensagem()` não tiver unicidade por `MSG_MetaMessageId`.
13. **Payload/logs podem conter dados sensíveis**: retornos da Meta são salvos em tabelas e logs; há sanitização em alguns pontos de UI, mas o worker/webhook persistem JSON completo.

## 10. Proposta inicial do Worker contínuo

Criar uma arquitetura de Worker contínuo por serviços, preservando o comportamento atual em uma primeira fase:

1. **`WorkerRunner` CLI**: loop controlado por sinais, intervalo configurável, limite por ciclo, logs estruturados e healthcheck simples.
2. **Lock compartilhado**: manter `flock` local para compatibilidade, mas adicionar lock por banco para ambientes com múltiplas cópias. Exemplo: tabela de locks ou `GET_LOCK()`/`RELEASE_LOCK()` por banco MySQL.
3. **Processadores separados**:
   - `DisparoManualQueueProcessor`, inicialmente encapsulando `DisparoManualQueueService`.
   - `CampanhaQueueProcessor`, extraído de `worker.php`, com reserva atômica equivalente a `UPDATE ... WHERE FIL_Status = 'pendente'` e checagem de `rowCount()`.
4. **Validador operacional por cliente**: serviço que avalie status financeiro/trial/plano/Meta antes de enviar cada lote ou fatia, sem depender de sessão/Auth HTTP.
5. **Modelo comum de resultado de envio**: objeto/array padronizado para sucesso, erro temporário, erro permanente, rate limit, message id e payload sanitizado.
6. **Retry/backoff persistente**: usar `FIL_Tentativas` e, futuramente, adicionar campos equivalentes ao lote manual antes de habilitar retries sofisticados para `disparo_manual_itens`.
7. **Requeue de itens órfãos**: rotina segura para `processando` antigo sem `MessageId`, com janela de expiração.
8. **Idempotência**: antes de reenviar, checar se o item já possui `MessageId` ou status final; para novas estruturas, considerar chave lógica por origem (`campanha`, `lote_manual`), item id e tentativa.
9. **Reconciliador de status**: manter webhook como caminho principal, mas prever job de reconciliação/relatórios para itens `aguardando_confirmacao` antigos.

## 11. Divisão recomendada da implementação em etapas

### Etapa 1 — Documentação e baseline

- Concluir este levantamento.
- Adicionar testes de sintaxe/execução segura para `worker.php` sem alterar comportamento.
- Mapear cron/systemd ativo em produção antes de mudanças.

### Etapa 2 — Extração sem mudança comportamental

- Extrair funções globais de `worker.php` para serviços/classes, mantendo mesma regra de seleção, limites e statuses.
- Criar `CampanhaQueueService` com métodos pequenos: buscar elegíveis, reservar campanha, buscar itens, reservar item, enviar, registrar sucesso/erro, finalizar.
- Cobrir com testes unitários onde possível e testes CLI em modo simulado.

### Etapa 3 — Concorrência e idempotência mínima

- Alterar reserva de `fila_envio` para `UPDATE ... WHERE FIL_ID = ? AND FIL_Status = 'pendente'` com checagem de `rowCount()`.
- Condicionar transição de campanha `agendada -> processando` ao status atual.
- Criar rotina de recuperação de `processando` antigo.
- Evitar reset/reenvio de itens com `MessageId` no reagendamento, ou exigir decisão explícita de reenviar.

### Etapa 4 — Bloqueios operacionais no CLI

- Criar serviço sem sessão para avaliar cliente liberado, trial, vencimento e plano.
- Aplicar antes do processamento de campanha/lote.
- Registrar motivo de pausa/bloqueio sem marcar destinatários como erro definitivo.

### Etapa 5 — Retry/backoff e rate limit

- Diferenciar erro temporário/permanente.
- Usar `FIL_Tentativas` e próxima tentativa para campanhas.
- Planejar migração futura para tentativas em `disparo_manual_itens`.
- Persistir pausa por conta Meta/cliente quando a Meta retornar rate limit.

### Etapa 6 — Worker contínuo real

- Implementar loop contínuo com sleep configurável, shutdown gracioso e métricas.
- Manter compatibilidade com execução one-shot para cron, se necessário.
- Atualizar documentação de deploy systemd.

### Etapa 7 — Observabilidade e reconciliação

- Logs estruturados com `CLI_ID`, `MTA_ID`, origem, item id e message id, sem token.
- Painel/consulta de filas presas.
- Job de reconciliação para `aguardando_confirmacao` antigo.

## 12. Decisões pendentes

1. O Worker contínuo deve substituir cron/systemd one-shot atual ou manter ambos os modos?
2. Será permitido processar múltiplas contas/clientes em paralelo ou o limite global de `WHATSAPP_ENVIOS_POR_SEGUNDO` continuará único?
3. O limite/rate limit deve ser global, por `MTA_ID`, por WABA ou por cliente?
4. Qual regra de bloqueio financeiro/trial deve pausar filas já criadas: pausar campanha/lote, marcar erro, ou manter pendente até regularização?
5. Quantas tentativas e qual backoff para erros temporários da Meta?
6. Quais erros da Meta são permanentes versus temporários para o domínio do produto?
7. Como tratar campanhas reagendadas após envio parcial: reenviar somente pendentes, clonar campanha, ou permitir reenvio explícito?
8. Campanhas devem passar a gravar também em `disparos` para unificar histórico?
9. O status `concluido_com_erros` deve ser efetivamente gravado em `disparo_manual_lotes`?
10. Será criada tabela única de jobs/envios ou manteremos `fila_envio` e `disparo_manual_itens` separadas?
11. Qual estratégia de deduplicação será adotada para webhooks reenviados pela Meta?
12. Qual retenção/sanitização será aplicada para `*_Retorno` e logs contendo payloads externos?

## Implementação — Etapa 1

Esta etapa iniciou a fundação técnica para execuções repetidas do Worker sem transformar o processo em daemon/loop infinito. A execução compatível continua sendo `php worker.php`, com um único ciclo e encerramento.

### Services criados

- `app/Services/WorkerService.php`
  - Gera `worker_id` no formato `hostname-pid-token`.
  - Executa um ciclo único do Worker.
  - Coordena recuperação de itens travados, lotes manuais e campanhas.
  - Retorna resumo estruturado com início, fim, duração, lotes manuais, campanhas, recuperados e exceções.
  - Registra logs estruturados em `storage/logs/worker.log` sem payload completo sensível.

- `app/Services/CampanhaQueueService.php`
  - Ativa campanhas `agendada` elegíveis para `processando`.
  - Busca campanhas em processamento.
  - Carrega template, variáveis e itens pendentes.
  - Reserva atomicamente cada item de `fila_envio` antes do envio.
  - Envia via `MetaService::enviarTemplate()` preservando a interface pública do serviço.
  - Padroniza internamente o resultado do envio em `aceito_meta`, `erro_temporario` ou `erro_definitivo`.
  - Registra sucesso, erro, bloqueio operacional e finaliza campanhas sem pendentes/processando.

- `app/Services/WorkerOperationalValidatorService.php`
  - Valida o cliente em contexto CLI, sem sessão HTTP e sem redirecionamentos.
  - Verifica cliente existente, `CLI_Ativo`, `CLI_StatusCadastro`, situação financeira/trial, limite mensal, conta Meta ativa/configurada e formato mínimo do número.
  - Retorna classificação `permitido`, `bloqueio_temporario` ou `bloqueio_definitivo`, com código e mensagem.

### Lógica extraída de `worker.php`

O `worker.php` ficou restrito a bootstrap, autoload, configuração de log de erro, lock local, criação do `WorkerService`, execução de um ciclo, impressão do resumo JSON e código de saída. A lógica de campanhas que antes estava procedural foi movida para `CampanhaQueueService`; a orquestração do ciclo foi movida para `WorkerService`.

### Proteção de concorrência implementada

A reserva de itens de campanha passou a usar transição condicional em `fila_envio`:

```sql
UPDATE fila_envio
SET
    FIL_Status = 'processando',
    FIL_Tentativas = FIL_Tentativas + 1
WHERE FIL_ID = ?
AND FIL_Status = 'pendente'
```

O item só é enviado quando `rowCount() === 1`, evitando o padrão inseguro de selecionar pendentes e enviar sem confirmar posse. O lote manual já possuía proteção equivalente em `disparo_manual_itens` e foi mantido.

O lock local `storage/worker.lock` foi preservado para compatibilidade com a execução atual em uma única cópia do projeto. Ele ainda não substitui um lock compartilhado entre múltiplas VPS, containers ou diretórios de release.

### Recuperação de itens travados

Foi adicionada a constante `WORKER_PROCESSING_TIMEOUT_MINUTES`, com valor inicial de 15 minutos.

Para disparos manuais, a recuperação é compatível com o banco atual porque `disparo_manual_itens` possui `DMI_DataAtualizacao`. O Worker retorna itens `processando` para `pendente` apenas quando:

- `DMI_Status = 'processando'`;
- `DMI_MessageId IS NULL`;
- `DMI_DataAtualizacao` é anterior ao timeout configurado.

Para campanhas, a recuperação ficou conservadora por limitação do schema atual. A tabela `fila_envio` não possui coluna de data de reserva/atualização do estado `processando`; portanto a rotina só recupera linhas `processando` sem `FIL_MessageId` se houver uma data confiável em `FIL_DataEnvio` mais antiga que o timeout. Como a reserva atual não grava `FIL_DataEnvio`, a recuperação efetiva de campanhas depende da migration recomendada abaixo.

### Validação operacional no Worker

Antes de enviar mensagens em lote manual e campanha, o Worker valida explicitamente:

- cliente encontrado;
- `CLI_Ativo = 'S'`;
- `CLI_StatusCadastro = 'ativo'`;
- financeiro pago ou trial/tolerância válido;
- limite mensal do plano;
- conta Meta pertencente ao cliente e ativa;
- token, `MTA_PhoneNumberId` e `MTA_UrlBase` preenchidos;
- `MTA_Status` não claramente bloqueado (`erro`, `desconectado`, `requer_acao`);
- número com tamanho mínimo operacional após normalização.

Quando a classificação não é totalmente clara, a etapa preserva comportamento permissivo. Por exemplo, status Meta vazio ou legado não é bloqueado automaticamente; somente estados explicitamente problemáticos são tratados como bloqueio temporário.

### Padronização do resultado de envio

`CampanhaQueueService` e `DisparoManualQueueService` adaptam a resposta atual de `MetaService::enviarTemplate()` para um resultado interno com:

- `sucesso`;
- `message_id`;
- `tipo_resultado`;
- `retry`;
- `erro_codigo`;
- `erro_mensagem`.

A interface pública de `MetaService` não foi alterada.

### Logs e resumo do ciclo

Os logs de ciclo registram `worker_id`, início, fim, duração, itens reservados, enviados, erros, bloqueios, recuperações e exceções. A saída CLI agora imprime um JSON do resumo do ciclo, que poderá ser reutilizado por loop contínuo ou monitoramento futuro.

### Limitações impostas pelo banco atual

- `fila_envio` não tem coluna para `worker_id`, data de reserva, data de atualização, próxima tentativa ou classificação do último erro.
- `disparo_manual_itens` não tem contador de tentativas nem próxima tentativa.
- Não há lock compartilhado no banco.
- Não há chave de idempotência por origem/item/tentativa.
- Erros temporários ainda são persistidos como `erro`, preservando a estrutura atual; retry/backoff persistente depende de novas colunas.
- A recuperação de campanhas é intencionalmente limitada porque não existe timestamp confiável de reserva no schema atual.

### Migrations recomendadas para a próxima etapa

- Adicionar em `fila_envio`:
  - `FIL_WorkerId` VARCHAR;
  - `FIL_DataReserva` DATETIME;
  - `FIL_DataAtualizacao` DATETIME;
  - `FIL_ProximaTentativa` DATETIME;
  - `FIL_UltimoErroTipo` VARCHAR;
  - índice por `FIL_Status`, `FIL_ProximaTentativa`, `FIL_DataReserva`.

- Adicionar em `disparo_manual_itens`:
  - `DMI_Tentativas` INT;
  - `DMI_WorkerId` VARCHAR;
  - `DMI_DataReserva` DATETIME;
  - `DMI_ProximaTentativa` DATETIME;
  - `DMI_UltimoErroTipo` VARCHAR.

- Criar mecanismo de lock compartilhado:
  - tabela própria de locks do Worker; ou
  - uso controlado de `GET_LOCK()`/`RELEASE_LOCK()` no MySQL.

- Avaliar chave lógica de idempotência para envios:
  - origem (`campanha`/`manual`);
  - id do item;
  - tentativa;
  - `message_id` Meta quando existir.

### Decisões que ainda precisam de validação

1. Quais estados Meta além de `erro`, `desconectado` e `requer_acao` devem bloquear envio.
2. Se limite mensal atingido deve pausar filas ou marcar itens como erro operacional.
3. Se bloqueios financeiros/trial devem congelar campanha/lote em vez de marcar item individual como erro.
4. Quantas tentativas usar para erros temporários e qual backoff.
5. Como reconciliar campanhas parcialmente enviadas antes de reagendamento.
6. Se campanhas devem registrar também na tabela `disparos` para unificar histórico.
7. Qual estratégia final de lock distribuído será adotada em produção.

## Implementação — Etapa 2

A Etapa 2 adicionou persistência de reserva e retry/backoff para o Worker, sem alterar o modelo de execução de `php worker.php` como ciclo único.

Principais entregas:

- migration `database/migrations/20260714_add_worker_retry_fields.sql` para campos de Worker, reserva, próxima tentativa, último erro e tentativas em `fila_envio`/`disparo_manual_itens`;
- `Services\WorkerRetryPolicyService` para cálculo centralizado de backoff, jitter, limite de tentativas e classificação de erros;
- reserva persistente de campanhas com `FIL_WorkerId`, `FIL_DataReserva`, `FIL_DataAtualizacao`, `FIL_ProximaTentativa` e incremento controlado de `FIL_Tentativas`;
- reserva persistente de disparos manuais em background com campos equivalentes `DMI_*`;
- recuperação de itens travados baseada em `DataReserva` e ausência de `MessageId`;
- falhas temporárias retornam para `pendente` com próxima tentativa;
- falhas definitivas limpam reserva e gravam tipo/código do erro;
- bloqueios temporários antes da chamada Meta compensam a tentativa incrementada na reserva;
- sucesso limpa Worker/reserva/próxima tentativa/último erro antes de seguir com consumo e conversa;
- cenário de persistência pós-envio tenta gravar `MessageId` de emergência e não devolve o item diretamente para reenvio.

Limitações restantes:

- a migration precisa ser aplicada manualmente em produção antes de executar o novo Worker;
- a idempotência completa ainda depende de chave lógica/tabela própria de envios;
- o estado `parcial` de campanha não foi introduzido para manter compatibilidade com os estados atuais;
- o lock compartilhado foi implementado com `GET_LOCK()` via conexão PDO singleton, mantendo `flock` local como camada adicional.
