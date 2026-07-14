# Auditoria técnica — Worker contínuo — Etapa 1

## 1. Resumo da auditoria

Esta auditoria revisou a fundação do Worker criada no commit `6c93383 refactor: preparar processamento seguro do worker`, comparando o fluxo anterior de `worker.php` e `DisparoManualQueueService.php` com a versão refatorada em `WorkerService`, `CampanhaQueueService` e `WorkerOperationalValidatorService`.

A análise confirmou que a extração preservou os principais passos do processamento de campanhas: ativar campanhas agendadas, selecionar campanhas `processando`, buscar itens `pendente`, montar variáveis a partir de `CON_DadosJson` e `campanha_variaveis`, enviar via `MetaService::enviarTemplate()`, gravar `FIL_MessageId`, atualizar `fila_envio`, incrementar consumo, registrar conversa e finalizar campanha quando não há itens `pendente`/`processando`.

Também foram encontrados riscos relevantes introduzidos ou expostos pela Etapa 1. Dois foram corrigidos nesta auditoria sem criar migrations ou novas funcionalidades:

1. bloqueios temporários eram gravados como `erro`, impedindo reprocessamento;
2. a validação operacional adicionada ao `DisparoManualQueueService` afetava também o caminho AJAX de processamento manual, alterando comportamento fora do Worker.

Não foi executado `php worker.php`, para evitar consumo de filas reais ou chamadas externas à Meta.

## 2. Paridade entre fluxo antigo e novo

### 2.1 Campanhas agendadas

| Ponto | Fluxo antigo em `worker.php` | Fluxo novo | Paridade |
|---|---|---|---|
| Ativação de campanhas | `UPDATE campanhas SET CAM_Status = 'processando' WHERE CAM_Status = 'agendada' AND CAM_DataAgendamento <= NOW()` | `CampanhaQueueService::ativarCampanhasAgendadas()` executa a mesma transição | Preservada |
| Seleção de campanhas | `SELECT * FROM campanhas WHERE CAM_Status = 'processando'` | `CampanhaQueueService::buscarCampanhasProcessando()` seleciona `processando`, com ordenação explícita | Preservada, com ordenação adicional |
| Template ausente | Cancela campanha (`CAM_Status = 'cancelada'`) | Cancela campanha | Preservada |
| Variáveis | Busca `campanha_variaveis` por `CAM_ID`, ordenando por `CAST(CPV_Variavel AS UNSIGNED)` | Mesmo critério | Preservada |
| Itens da fila | Busca `fila_envio` pendente por campanha, ordena por `FIL_ID`, limita por execução | Mesmo critério | Preservada |
| Reserva | Antigo marcava `processando` sem condicionar status | Novo usa `UPDATE ... WHERE FIL_ID = ? AND FIL_Status = 'pendente'` e exige `rowCount() === 1` | Melhorada |
| Conta Meta | Antigo usava `new MetaService($template['MTA_ID'])` | Novo usa `new MetaService($metaId, $clienteId)` | Diferença intencional: restringe por cliente |
| Header de mídia | `midiaHeaderCampanha($campanha)` | `CampanhaQueueService::midiaHeaderCampanha()` | Preservada |
| Envio | `MetaService::enviarTemplate()` | `MetaService::enviarTemplate()` | Preservada |
| `message_id` | Grava em `FIL_MessageId` | Grava em `FIL_MessageId` | Preservada |
| `disparos` | O worker antigo não gravava campanha em `disparos` | O novo também não grava | Paridade preservada, lacuna mantida |
| Consumo | Incrementa `ConsumoMensal` e `ControlePlanoService` apenas se há `messages[0].id` | Mesmo comportamento | Preservada |
| Conversa | Cria/atualiza conversa e salva mensagem enviada | Mesmo comportamento | Preservada |
| Erro Meta | Marca `FIL_Status = 'erro'`, grava erro/retorno e incrementa `CAM_TotalErros` | Mesmo para erro definitivo/Meta; bloqueio temporário foi corrigido para voltar a `pendente` | Parcialmente alterada por segurança |
| Finalização | Finaliza quando não há `pendente`/`processando` | Mesmo critério | Preservada |

### 2.2 Lotes manuais

O fluxo antigo de `DisparoManualQueueService` já tinha reserva condicional de `disparo_manual_itens` com `DMI_Status = 'pendente'`. A Etapa 1 preservou essa proteção e adicionou validação operacional, resumo com `worker_id` e recuperação de travados.

Diferença corrigida nesta auditoria: a validação operacional passou a ser aplicada apenas quando a origem não é `ajax`, para preservar o comportamento do processamento manual existente acionado por tela/AJAX. O Worker continua validando lotes manuais quando usa origem `worker`/`cron`.

## 3. Problemas críticos

### 3.1 Envio aceito pela Meta e falha posterior no banco

- **Arquivo:** `app/Services/CampanhaQueueService.php`; `app/Services/DisparoManualQueueService.php`.
- **Métodos:** `registrarSucesso()` e chamadas subsequentes após `MetaService::enviarTemplate()`.
- **Comportamento atual:** se a Meta aceitar a mensagem e retornar `message_id`, mas a atualização local falhar antes de persistir `FIL_MessageId`/`DMI_MessageId`, o item pode permanecer `processando` ou sem rastreamento completo. Em uma execução futura, se for recuperado para `pendente`, pode ocorrer reenvio da mesma mensagem.
- **Risco:** duplicidade real de mensagens, consumo incorreto e perda de correlação com webhook.
- **Correção realizada:** nenhuma correção completa nesta etapa, por exigir idempotência/reconciliação e possivelmente schema novo. O risco foi documentado como dependente de migration e Etapa 2.
- **Recomendação:** adicionar chave de idempotência por item/origem/tentativa, persistência de reserva antes do envio, reconciliação por message id e rotina para itens com envio aceito mas persistência parcial.

## 4. Problemas altos

### 4.1 Bloqueio temporário era tratado como erro definitivo

- **Arquivo:** `app/Services/CampanhaQueueService.php`; `app/Services/DisparoManualQueueService.php`.
- **Métodos:** `registrarBloqueio()`; trecho de validação em `processarItens()`.
- **Comportamento anterior da Etapa 1:** qualquer retorno não permitido do validador era persistido como `erro`.
- **Risco:** bloqueios temporários, como financeiro regularizável, trial/limite ou conta Meta temporariamente indisponível, perdiam possibilidade de reprocessamento e poderiam finalizar campanhas prematuramente.
- **Correção realizada:** bloqueio temporário agora volta o item para `pendente`, preservando `FIL_Tentativas`/dados úteis; bloqueio definitivo continua indo para `erro`.
- **Observação:** sem `FIL_ProximaTentativa`/`DMI_ProximaTentativa`, o item temporário pode ser tentado novamente no próximo ciclo. Isso é aceito temporariamente até a migration de backoff.

### 4.2 Validação operacional alterava fluxo AJAX manual

- **Arquivo:** `app/Services/DisparoManualQueueService.php`.
- **Método:** `processarItens()` chamado por `processarLote()` e `processarPendentes()`.
- **Comportamento anterior da Etapa 1:** a validação operacional era aplicada também quando a origem do processamento era `ajax`.
- **Risco:** alteração de comportamento da tela/processamento manual existente fora do escopo do Worker CLI; poderia bloquear envios manuais que anteriormente eram tratados pelo fluxo HTTP existente.
- **Correção realizada:** a validação operacional agora é aplicada apenas quando a origem não é `ajax`. Assim, o Worker/cron valida explicitamente, preservando o comportamento AJAX.

### 4.3 Recuperação de campanhas travadas ainda não é efetiva sem migration

- **Arquivo:** `app/Services/CampanhaQueueService.php`.
- **Método:** `recuperarTravados()`.
- **Comportamento atual:** a recuperação exige `FIL_DataEnvio IS NOT NULL` e data antiga, mas a reserva não grava data de reserva no schema atual.
- **Risco:** itens de campanha que morrem após reserva e antes de envio ficam em `processando` até intervenção manual ou migration.
- **Correção realizada:** nenhuma alteração de schema permitida. A recuperação permanece conservadora para não reprocessar envio possivelmente ativo.
- **Recomendação:** adicionar `FIL_DataReserva`, `FIL_WorkerId`, `FIL_DataAtualizacao` e `FIL_ProximaTentativa`.

## 5. Problemas médios

### 5.1 Campanhas continuam sem registro em `disparos`

- **Arquivo:** `app/Services/CampanhaQueueService.php`.
- **Método:** `registrarSucesso()`.
- **Comportamento atual:** paridade com o worker antigo: campanhas registram `fila_envio` e `conversa_mensagens`, mas não inserem em `disparos`.
- **Risco:** histórico unificado e consulta por `Disparo::buscarPorMessageIds()` não cobrem campanhas.
- **Correção realizada:** nenhuma, por ser lacuna pré-existente e fora do escopo de correção da auditoria.
- **Recomendação:** decidir na Etapa 2 se campanhas devem gravar `disparos`.

### 5.2 `CampanhaQueueService` acumula responsabilidades

- **Arquivo:** `app/Services/CampanhaQueueService.php`.
- **Métodos:** classe inteira.
- **Comportamento atual:** o service concentra ativação de campanhas, reserva de fila, montagem de parâmetros, envio, persistência de resultado, sanitização, rate limit e finalização.
- **Risco:** manutenção/testabilidade mais difíceis à medida que retry/backoff e idempotência forem adicionados.
- **Correção realizada:** nenhuma divisão estética nesta etapa.
- **Recomendação:** só dividir na Etapa 2 quando forem adicionadas responsabilidades independentes claras, por exemplo `CampanhaFilaRepository`, `ResultadoEnvioAdapter` e `CampanhaResultadoService`.

### 5.3 Código de saída para execução concorrente é 0

- **Arquivo:** `worker.php`.
- **Método/trecho:** falha ao adquirir `flock`.
- **Comportamento atual:** quando outro worker está em execução, o script encerra com `exit(0)`.
- **Risco:** monitoramento pode interpretar concorrência como sucesso normal.
- **Correção realizada:** nenhuma, porque encerrar sem erro em concorrência era comportamento compatível com o worker anterior.
- **Recomendação:** decidir código específico ou resumo JSON para lock não adquirido em etapa de observabilidade.

## 6. Observações de baixo risco

### 6.1 `PDOStatement::rowCount()` no `UPDATE`

- **Arquivo:** `app/Services/CampanhaQueueService.php`.
- **Método:** `reservarItem()`.
- **Análise:** para MySQL/MariaDB via PDO, `rowCount()` é adequado para `UPDATE` e retorna linhas afetadas. Como a transição muda `FIL_Status` de `pendente` para `processando` e incrementa `FIL_Tentativas`, uma reserva válida deve afetar uma linha. Se outro processo reservar antes, a condição `FIL_Status = 'pendente'` falha e `rowCount()` fica 0.
- **Risco residual:** comportamento depende do driver PDO MySQL/MariaDB, que é o banco documentado para produção.

### 6.2 Compatibilidade PHP

- **Evidências localizadas:** `composer.json` não define versão mínima de PHP; `composer.lock` contém dependências compatíveis com PHP 7.2+/8.x e `maennchen/zipstream-php` exigindo PHP >= 8.0; documentação menciona PHP CLI compatível na VPS/Hostinger, mas não fixa versão exata.
- **Análise do código novo:** usa tipagem de parâmetros/retornos e nullable type `?WorkerOperationalValidatorService`, recursos compatíveis com PHP 7.1+. Não usa `match`, union types, nullsafe operator, named arguments, typed properties ou arrow functions.
- **Risco:** se produção fosse PHP < 7.1, o projeto já teria incompatibilidades de dependências; para PHP 8.x esperado por dependências, o código novo é compatível.

### 6.3 Logs e credenciais

- **Arquivos:** `worker.php`, `WorkerService`, `CampanhaQueueService`.
- **Análise:** logs de ciclo sanitizam chaves sensíveis por padrão textual; `CampanhaQueueService::retornoSeguro()` remove `payload` do retorno persistido para campanhas. Buscas por `MTA_Token`, `Authorization` e `Bearer` não indicaram logs diretos desses valores nos novos services.
- **Risco residual:** retornos externos salvos por fluxos legados ainda podem conter payloads ou mensagens de erro extensas; a correção completa depende de política geral de sanitização.

## 7. Correções realizadas

### 7.1 Reclassificação de bloqueios temporários

- `CampanhaQueueService::registrarBloqueio()` agora grava `FIL_Status = 'pendente'` quando `status = bloqueio_temporario`, mantendo possibilidade de reprocessamento.
- `DisparoManualQueueService::registrarBloqueioOperacional()` agora grava `DMI_Status = 'pendente'` para bloqueio temporário.
- Bloqueios definitivos continuam sendo gravados como `erro`, evitando loop infinito.

### 7.2 Preservação do fluxo AJAX manual

- `DisparoManualQueueService::processarItens()` agora executa `WorkerOperationalValidatorService` somente quando a origem não é `ajax`.
- O Worker continua usando origem `worker`; chamadas cron/worker seguem validadas.

## 8. Riscos que dependem de migration

1. **Recuperação confiável de campanhas:** depende de `FIL_DataReserva`/`FIL_DataAtualizacao` e `FIL_WorkerId`.
2. **Retry/backoff persistente:** depende de `FIL_ProximaTentativa`, `DMI_ProximaTentativa`, tentativas e tipo do último erro.
3. **Idempotência:** depende de chave lógica por origem/item/tentativa ou tabela de envios.
4. **Lock compartilhado:** depende de tabela de lock ou uso controlado de `GET_LOCK()`.
5. **Reprocessamento seguro após Meta aceitar e banco falhar:** depende de persistência idempotente e reconciliação.

## 9. Riscos aceitos temporariamente

1. O Worker ainda usa lock local por arquivo, adequado apenas para uma cópia do projeto.
2. Campanhas ainda não gravam `disparos`, preservando paridade com o worker antigo.
3. Bloqueios temporários voltam para `pendente` sem próxima tentativa, podendo ser reavaliados no próximo ciclo.
4. Não há limite de quantidade por ciclo para recuperação manual além do próprio `UPDATE` conservador.
5. A saída JSON só ocorre quando o ciclo é executado; lock concorrente encerra silenciosamente com código 0, preservando compatibilidade anterior.

## 10. Recomendação objetiva para a Etapa 2

A Etapa 2 deve priorizar migrations mínimas para tornar a segurança operacional verificável:

1. adicionar colunas de reserva (`WorkerId`, `DataReserva`, `DataAtualizacao`) em `fila_envio` e `disparo_manual_itens`;
2. adicionar `ProximaTentativa`, `Tentativas` e tipo/código do último erro;
3. implementar retry/backoff apenas após essas colunas;
4. criar lock compartilhado por banco;
5. projetar idempotência para o cenário crítico de Meta aceitar mensagem e a persistência local falhar;
6. decidir se campanhas passam a registrar `disparos`.

Sem essas mudanças, a fundação atual melhora a reserva atômica e a organização do código, mas ainda não deve ser usada com múltiplos workers simultâneos em produção.
