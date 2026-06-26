# Auditoria técnica de conexões com banco

Branch: `auditoria-conexoes-banco`  
Data: 2026-06-25  
Escopo: diagnóstico, sem alteração funcional.

## 1. Conclusão principal

O código centraliza a conexão MySQL em `Core\Database::getInstance()` e, dentro de um mesmo processo/request PHP, o objeto `PDO` é singleton estático. Não foi encontrado uso de `mysqli_connect`, `new mysqli`, `PDO::__construct` fora de `app/Core/Database.php`, nem criação direta de `new PDO` fora do singleton.

Portanto, o estouro `max_connections_per_hour = 500` parece menos relacionado a múltiplas conexões simultâneas acumuladas em `SHOW PROCESSLIST` e mais relacionado à quantidade de **novas execuções PHP por hora** que chamam banco: cada request web, chamada AJAX/polling, webhook, cron ou execução CLI abre uma nova conexão física ao MySQL. Em hospedagem compartilhada, mesmo uma aplicação que abre só 1 conexão por request pode atingir 500 conexões/hora se houver polling, abas abertas, webhooks e workers/cron frequentes.

A hipótese mais provável é uma combinação de:

1. **Auth/financeiro**: quase toda rota autenticada chama banco pelo menos uma vez em `Auth::check()`/`validarBloqueioFinanceiro()`/`atualizarStatusCliente()`.
2. **Polling da Central de Conversas**: roda a cada 60 segundos por aba visível com conversa aberta e pode gerar chamadas extras (`verificarAtualizacao`, `ajaxLista`, `ajaxMensagens`).
3. **Disparo Manual AJAX**: após criar lote, chama `processarLoteAjax` a cada ~2 segundos enquanto houver pendências e também mantém `statusLoteAjax` a cada 7 segundos.
4. **Worker/cron**: `worker.php`, `processar_vencimentos.php` e `gerar_cobrancas_recorrentes.php` abrem conexão a cada execução.
5. **Webhooks Meta/Asaas**: cada evento recebido abre uma conexão; webhooks podem vir em rajadas.

## 2. Camada de banco

### 2.1 Implementação atual

Arquivo auditado: `app/Core/Database.php`.

- `Database::getInstance()` mantém `private static $instance`.
- Se `self::$instance` ainda não existir, cria `new PDO(...)`.
- Depois retorna sempre a mesma instância dentro do mesmo processo PHP.
- Em erro, registra log em arquivo e encerra com HTTP 500.

### 2.2 Veredito sobre singleton

O singleton funciona **por request/processo PHP**, mas não compartilha conexão entre requests diferentes. Em PHP-FPM/Apache compartilhado, cada request que chama `Database::getInstance()` pode criar uma conexão nova. Em CLI/cron, cada execução também cria uma conexão nova.

Isso explica o cenário relatado: `SHOW PROCESSLIST` pode não mostrar acúmulo, porque as conexões são curtas, mas o contador da Hostinger sobe por quantidade de novas conexões/hora.

### 2.3 Locais com conexão direta fora do singleton

Auditoria por `rg` para:

- `new PDO`
- `mysqli_connect`
- `new mysqli`
- `PDO::__construct`
- `Database::connect`

Resultado:

- `new PDO` aparece apenas em `app/Core/Database.php`.
- Não foi encontrado `mysqli_connect`.
- Não foi encontrado `new mysqli`.
- Não foi encontrado `PDO::__construct`.
- Não foi encontrado `Database::connect`.

Não há evidência de conexão direta fora do singleton.

## 3. Locais que usam `Database::getInstance()`

### Core

- `app/Core/Database.php`
- `app/Core/Auth.php`

### Controllers

- `app/Controllers/LoginController.php`
- `app/Controllers/DashboardController.php`
- `app/Controllers/FinanceiroController.php`
- `app/Controllers/FinanceiroAdminController.php`
- `app/Controllers/ClienteController.php`
- `app/Controllers/SiteController.php`

Observação: outros controllers abrem conexão indiretamente ao instanciar Models ou Services.

### Models

Todos os models relevantes usam o singleton no construtor:

- `Assinatura`
- `Campanha`
- `CampanhaVariavel`
- `Cliente`
- `Cobranca`
- `ConsumoMensal`
- `Contato`
- `Conversa`
- `Disparo`
- `DisparoManual`
- `ExcedenteMensal`
- `FilaEnvio`
- `ListaContato`
- `ListaContatoItem`
- `MetaConta`
- `Plano`
- `TemplateMeta`
- `Usuario`

### Services

- `MetaService`
- `DisparoManualQueueService`
- `FinanceiroRecorrenciaService`

Não foi encontrada conexão de banco em:

- `AsaasService`
- `InterService`
- `ControlePlanoService`

### Scripts CLI / cron / webhooks

- `worker.php`
- `public/webhook/meta.php`
- `processar_vencimentos.php` via `FinanceiroRecorrenciaService`
- `gerar_cobrancas_recorrentes.php` via `FinanceiroRecorrenciaService`

## 4. Contagem estimada de conexões por fluxo

Importante: as contagens abaixo são de **conexões físicas novas por execução/request**, não de queries. Dentro da mesma execução, múltiplos models compartilham o mesmo `PDO`.

| Fluxo | Conexões físicas por request/execução | Observações |
|---|---:|---|
| Login GET | 0 | Renderiza view sem banco. |
| Login POST | 1 | `LoginController::autenticar()` chama `Database::getInstance()`. |
| Dashboard | 1 | `Auth::check()` abre banco e o controller reutiliza. Muitas queries, mas uma conexão. |
| Financeiro/index | 1 | Auth + vários models (`Plano`, `Cobranca`, `MetaConta`, `Assinatura`, `ExcedenteMensal`) compartilham a conexão. |
| Financeiro/escolherPlano | 1 | Auth + transação + models + AsaasService. AsaasService não abre banco. |
| Integração Asaas na escolha de plano | 0 adicional | Usa models/controller já no mesmo request; chamadas HTTP externas não são conexão MySQL. |
| Asaas webhook | 0 ou 1 | Sem `payment.id` retorna antes do banco; com `payment.id` instancia `Cobranca` e abre 1 conexão. |
| Meta webhook | 1 | `public/webhook/meta.php` chama `Database::getInstance()` logo no início. |
| Disparo Manual criar lote | 1 | Auth + models. Pode executar muitas queries/inserts, mas uma conexão por request. |
| Disparo Manual processar lote AJAX | 1 por chamada AJAX | Chama `DisparoManualQueueService`, `MetaService` por conta/cache, models. Uma conexão por chamada, mas chamadas podem ocorrer a cada 2s. |
| Worker `worker.php` | 1 por execução CLI | Reutiliza conexão no processo; se cron roda muitas vezes por hora, soma rápido. |
| Central de Conversas tela inicial | 1 | Auth + `Conversa` + possivelmente `Usuario`. |
| Polling Central de Conversas | 1 por chamada | `verificarAtualizacao` roda a cada 60s por aba visível com conversa aberta; se houver atualização, chama também `ajaxLista` e `ajaxMensagens`. |
| Polling Disparo Manual | 1 por chamada | `statusLoteAjax` a cada 7s; `processarLoteAjax` pode rodar a cada 2s enquanto houver pendências. |
| Site público/home/termos | 0 | Rotas estáticas não usam banco. |
| Site público/cadastro | 1 no POST | `SiteController` valida/cadastra usando banco. |

## 5. Financeiro / Asaas

Arquivos auditados:

- `app/Controllers/FinanceiroController.php`
- `app/Services/AsaasService.php`
- `app/Controllers/AsaasController.php`
- `app/Models/Cliente.php`
- `app/Models/Cobranca.php`
- `app/Models/Assinatura.php`

### Achados

1. `AsaasService` não abre conexão MySQL.
2. `FinanceiroController::index()` instancia vários models, mas todos usam o mesmo singleton por request.
3. `FinanceiroController::escolherPlano()` chama `Database::getInstance()` para transação e depois instancia/usa models; todos reutilizam o mesmo `PDO`.
4. `sincronizarCobrancaAsaas()` instancia `Cliente`, `Cobranca`, `AsaasService`; os dois models reutilizam o singleton já existente no request.
5. `AsaasController::webhook()` só chama banco se houver `payment.id`; nesse caso abre 1 conexão por webhook recebido.
6. Logs do Asaas são em arquivo, não banco.
7. Validações de colunas (`SHOW COLUMNS`) em models (`Cliente`, `Cobranca`) não abrem novas conexões, mas aumentam o número de queries. Como há cache estático por request, ainda assim repetem por request novo.

### Risco específico do Asaas

Durante testes, o financeiro pode gerar múltiplos requests POST de escolha de plano ou retries manuais. Cada tentativa abre 1 conexão. Webhooks do Asaas também somam 1 conexão cada quando contêm `payment.id`. Porém, isoladamente, Asaas não parece explicar 500 conexões/hora, a menos que haja retentativas/webhooks em loop ou muitos testes simultâneos.

## 6. Worker / fila

Arquivos auditados:

- `worker.php`
- `app/Services/DisparoManualQueueService.php`
- `app/Services/MetaService.php`
- Models usados pelo worker: `Disparo`, `Conversa`, `ConsumoMensal` etc.

### Achados

1. `worker.php` abre `Database::getInstance()` uma vez no início.
2. `DisparoManualQueueService` abre o singleton no construtor e instancia models (`Disparo`, `Conversa`, `ConsumoMensal`, `ControlePlanoService`). Esses models reutilizam a mesma conexão.
3. `MetaService` abre o singleton no construtor para buscar conta Meta. No processamento manual há cache por `MTA_ID:CLI_ID`, evitando recriar `MetaService` por item quando a conta é a mesma.
4. Dentro do loop de itens, não há nova conexão física por item, mas há muitas queries por item: reservar, enviar/registrar sucesso/erro, salvar disparo, consumo, conversa e recalcular lote.
5. `worker.php` também processa campanhas agendadas e, em cada item enviado com sucesso, instancia `ConsumoMensal`, `ControlePlanoService`, `Disparo`, `Conversa`. Ainda assim, por execução CLI, deve ser 1 conexão física, mas muitas queries.

### Risco do worker

Se o cron do worker estiver configurado com frequência alta (por exemplo, a cada minuto ou múltiplas entradas simultâneas), ele sozinho consome até 60 conexões/hora por entrada de cron. Se houver mais de um worker/cron concorrente, o número multiplica. O risco maior não é conexão por item, mas **execução recorrente/concorrente**.

## 7. Frontend / polling

### Central de Conversas

Arquivo principal: `app/Views/conversas/index.php`.

Achados:

- Existe polling por `setInterval(..., 60000)` para `conversa/verificarAtualizacao`.
- O polling só roda se a aba não está oculta (`document.hidden`) e existe conversa aberta.
- Ao detectar atualização, chama `ajaxLista` e, se houver conversa aberta, `ajaxMensagens`.
- Há flags para evitar simultaneidade (`atualizandoConversas`, `atualizandoListaConversas`, `atualizandoMensagens`).
- Ao voltar a aba para visível, executa verificação imediata.

Risco estimado:

- 1 aba com conversa aberta: ~60 conexões/hora só para verificação; com atualizações frequentes, pode virar ~120-180 conexões/hora.
- 3 abas/usuários: pode aproximar ou superar 500 conexões/hora, especialmente com webhooks/worker em paralelo.

### Disparo Manual

Arquivo principal: `public/assets/js/app.js`.

Achados:

- Após criar lote, chama `statusLoteAjax` imediatamente e agenda `setInterval(consultarLote, 7000)`.
- Também ativa `processarProximoBloco()`; enquanto houver pendências, agenda nova chamada a cada ~2 segundos em sucesso, ou 7-8 segundos em falha.
- Há flag `processandoBlocoLote` para evitar chamada simultânea do mesmo fluxo.
- O polling de status pode continuar se o lote concluir, pois não foi identificado `clearInterval(pollingLote)` no trecho auditado.
- Se o usuário voltar para edição (`btnVoltarEdicaoDisparo`), o trecho limpa variáveis visuais, mas não foi identificado `clearInterval(pollingLote)` ali.

Risco estimado:

- Durante processamento manual: `processarLoteAjax` pode gerar até ~30 conexões/minuto por aba/lote (a cada 2s) + `statusLoteAjax` ~8,5/minuto.
- Em poucos minutos de teste, isso pode consumir centenas de conexões/hora.
- Este é o ponto de maior risco imediato para `max_connections_per_hour`.

### Financeiro

Não foi encontrado polling específico no financeiro. O risco financeiro é por POST/GET normais e webhooks, não por polling contínuo.

### Login

`app/Views/auth/login.php` tem `setInterval` apenas para aguardar reCAPTCHA no frontend; não chama backend/banco.

## 8. Pontos de multiplicação por fluxo

### Muito perigosos

1. **Disparo Manual AJAX**: `processarLoteAjax` a cada 2s + `statusLoteAjax` a cada 7s.
2. **Cron/worker frequente ou duplicado**: cada execução abre 1 conexão; várias entradas duplicadas podem somar rapidamente.
3. **Central de Conversas aberta em várias abas/usuários**: 1 conexão/minuto por aba, mais chamadas extras quando há atualização.

### Moderados

1. **Meta webhook**: cada evento abre 1 conexão no início do script.
2. **Asaas webhook**: cada evento com `payment.id` abre 1 conexão.
3. **Dashboard/admin**: muitas queries, mas 1 conexão/request.

### Baixos

1. Login POST.
2. Site cadastro POST.
3. Financeiro/index e escolherPlano, desde que sem retry/refresh contínuo.

## 9. Instrumentação temporária recomendada

Não implementar nesta branch. Proposta para ambiente `local/dev` ou produção controlada por flag:

### Objetivo

Medir cada vez que `Database::getInstance()` cria conexão real, sem logar DSN, usuário, senha ou payloads.

### Local sugerido

`app/Core/Database.php`, imediatamente antes/depois do `new PDO`.

### Campos do log

- timestamp;
- origem: `cli` ou `web`;
- `REQUEST_METHOD`;
- `REQUEST_URI` sem query sensível, ou query mascarada;
- script (`$_SERVER['SCRIPT_NAME']` ou `PHP_SELF`);
- session id truncado/hash, se existir;
- IP mascarado ou hash;
- backtrace resumido: arquivo:linha + função/classes dos 5 primeiros frames;
- contador simples por processo/request;
- nunca incluir DB_HOST completo, DB_USER, DB_PASS, DSN, SQL ou payload.

### Exemplo de linha segura

```json
{
  "ts":"2026-06-25 12:00:00",
  "origem":"web",
  "method":"POST",
  "uri":"/index.php?url=disparo/processarLoteAjax",
  "script":"/index.php",
  "ip_hash":"a1b2c3",
  "trace":["Core\\Database::getInstance app/Models/DisparoManual.php:14", "Models\\DisparoManual->__construct app/Controllers/DisparoController.php:1100"]
}
```

### Arquivo sugerido

`storage/logs/database-connections.log`, com rotação manual simples ou limite de tamanho.

### Métrica complementar

Logar também, por middleware/front-controller, início/fim de request com URI e tempo. Assim é possível cruzar quantidade de requests com conexões reais.

## 10. Recomendações de correção

### Críticas — corrigir imediatamente antes de continuar Asaas

1. **Reduzir agressividade do Disparo Manual AJAX**
   - Aumentar intervalo de `processarLoteAjax` ou remover processamento por AJAX em produção, deixando o worker processar.
   - Encerrar `pollingLote` com `clearInterval()` quando lote concluir, falhar definitivamente, cancelar ou usuário voltar para edição.
   - Garantir que apenas uma aba/processamento por lote esteja ativo.

2. **Auditar cron na Hostinger**
   - Confirmar quantas entradas existem para `worker.php`, `processar_vencimentos.php` e `gerar_cobrancas_recorrentes.php`.
   - Garantir que não há cron duplicado.
   - Reduzir frequência do `worker.php` temporariamente se o limite continuar estourando.

3. **Instrumentar criação real de conexão**
   - Implementar log temporário em `Database::getInstance()` controlado por flag (`DB_CONNECTION_AUDIT=true`) para identificar os endpoints mais ativos.

### Importantes — corrigir antes do piloto

1. **Rever polling da Central de Conversas**
   - Aumentar intervalo para 2-5 minutos em hospedagem compartilhada, ou condicionar a atividade real do usuário.
   - Interromper polling quando painel não estiver focado, sem conversa aberta ou usuário navegar para outra tela.
   - Considerar endpoint único que retorne lista + mensagens quando necessário, reduzindo chamadas encadeadas.

2. **Cache de status financeiro em sessão**
   - `Auth::check()` atualiza status do cliente com banco em quase toda rota autenticada. Considerar cache por TTL curto em sessão (ex.: 60s) para reduzir queries e conexões em polling, com invalidação em rotas financeiras/webhooks.
   - Isso não reduz conexões se qualquer rota ainda precisar de banco por outro motivo, mas ajuda endpoints de polling simples.

3. **Evitar `SHOW COLUMNS` em runtime por request**
   - Remover compatibilidade dinâmica após migrations estabilizarem.
   - Cada request novo refaz checks por model; não abre nova conexão, mas aumenta custo de banco.

4. **Lock de worker/cron**
   - Criar lock file ou lock em banco para impedir execuções concorrentes de `worker.php`.

### Futuras — VPS/escala

1. Migrar processamento de fila para worker persistente/supervisor em VPS, com conexão reutilizada.
2. Usar Redis/filas para jobs e rate limiting.
3. Substituir polling por WebSocket/SSE quando infraestrutura permitir.
4. Considerar pool/conexões persistentes apenas em ambiente apropriado; em hospedagem compartilhada pode piorar limites de conexões simultâneas.
5. Separar banco para produção com limites maiores e métricas reais.

## 11. Próximos passos recomendados

1. Confirmar no painel da Hostinger todos os crons ativos e frequência real.
2. Implementar instrumentação temporária de conexão por request.
3. Rodar um teste controlado de 30 minutos com:
   - sem Central de Conversas aberta;
   - sem disparo manual AJAX;
   - sem worker;
   - apenas financeiro/Asaas.
4. Repetir habilitando um componente por vez:
   - worker;
   - Central de Conversas;
   - Disparo Manual.
5. Priorizar redução/encerramento do polling do Disparo Manual, pois é o candidato mais forte a consumir conexões rapidamente.

## 12. Comandos usados na auditoria

```bash
rg -n "new PDO|mysqli_connect|new mysqli|PDO::__construct|Database::getInstance|Database::connect|setInterval|setTimeout|fetch\(|ajax|\.load\(" app config public tests worker.php *.php docs database --glob '!vendor/**'
rg --files -g '*.php' -g '!vendor/**' | sort
rg -n "Database::getInstance|new PDO|mysqli_connect|new mysqli|PDO::__construct" app worker.php public/webhook/meta.php *.php --glob '!vendor/**'
```
