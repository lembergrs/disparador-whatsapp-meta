# Dashboard de onboarding guiado

## Objetivo e arquitetura

O Dashboard indica a próxima ação necessária para entregar a primeira mensagem pela API Oficial. Os módulos de conexão, pagamento, templates e Disparo Manual continuam executando suas próprias operações.

`DashboardController` obtém as permissões e os dados de avaliação do `Auth`, resolve a conta contextual com `OnboardingChecklistService` e renderiza `_onboarding.php`. `OnboardingReadModel` reúne somente evidências persistidas, por SELECT. O serviço interpreta essas evidências sem Graph, sincronização, início de trial ou alteração de estado. O painel administrativo não usa o guia.

O teste isolado do controller executa chamadas repetidas com conexão que rejeita comandos diferentes de SELECT e SQLite em modo `query_only`; nele, Auth, models auxiliares e layout são simulados. A leitura do serviço também foi validada em MySQL usando tabelas temporárias, sem alterar registros reais.

### Contrato de leitura de domínio

“Dashboard read-only” significa nenhuma escrita de domínio e nenhum efeito externo de negócio provocado pelo onboarding. Abrir a página não pode executar INSERT/UPDATE/DELETE de dados de negócio, iniciar/reiniciar trial, criar cobrança, alterar assinatura ou MetaConta, confirmar pagamento, sincronizar Meta, chamar Graph API, criar template/contato/lista/campanha ou enviar mensagem.

Sessão, logs, auditoria e observabilidade técnica preexistentes podem ocorrer em uma requisição normal, desde que não alterem estado de negócio. Não se promete ausência de escrita de qualquer espécie. A observabilidade financeira de suspensão permanece intacta.

### Avaliação de acesso compartilhada

O controller precisa de `Auth::clienteLiberado()` antes de calcular a próxima ação: sem essa decisão, um cliente suspenso com conexão/template prontos poderia receber um CTA de envio em vez do Financeiro. `Auth::check()` permite a rota Dashboard após atualizar a sessão, sem avaliar a política financeira nessa rota; os campos da sessão, sozinhos, não substituem essa avaliação.

O layout também consultava `Auth::clienteLiberado()` posteriormente. Agora recebe `acessoOperacionalDashboard` do controller e reutiliza o booleano, inclusive `false`. Na abertura normal do Dashboard de cliente com cadastro ativo, a política é avaliada uma vez. Se o Auth interrompe antes por cadastro inativo, nenhuma avaliação da política é necessária. Administradores não usam esse fluxo. Outras telas sem o resultado fornecido continuam chamando o Auth no layout como antes. Não há cache entre requisições nem cópia das regras financeiras no onboarding.

`DashboardReadOnlyTest` mantém o nome para evitar renomeação desnecessária, com semântica explicitamente limitada ao domínio da feature. `DashboardSuspendedClientTest` complementa a cobertura usando Auth, política financeira, models, controller e layout reais em SQLite isolado. O teste adapta apenas a introspecção MySQL de coluna para SQLite, permite logs técnicos em diretório temporário próprio, compara o estado de todas as tabelas antes/depois, protege SQL contra escrita e intercepta o transporte cURL usado pelos services para impedir chamadas externas de negócio. A renderização PHP não executa JavaScript do navegador.

## Conta contextual e multi-conta

Somente contas ativas do cliente autenticado entram na seleção. O parâmetro GET `conta` pode selecionar uma delas; IDs inválidos ou pertencentes a outro cliente não são aceitos nem aproveitam evidências de terceiros. Sem seleção explícita, a prioridade determinística é:

1. Conta com entrega comprovada, preservando a conquista após uma desconexão.
2. Conta conectada.
3. Maior ID como desempate.

Pagamento, templates, tentativas de envio e entrega são sempre lidos para o mesmo par cliente/conta. Os indicadores de WhatsApp do painel secundário usam essa mesma conta. Contagens gerais de contatos e campanhas continuam agregadas por cliente, sem participar da ativação.

A seleção é visível no Dashboard e não é persistida. As telas operacionais existentes mantêm seus próprios seletores; o guia orienta escolher nelas o mesmo número exibido. Esta branch não altera o contrato de navegação desses módulos. Contas inativadas ou excluídas deixam de ser candidatas: sem novo marco persistido, não se promete recuperar sua conquista histórica.

## Etapas e critérios

| Ordem | Etapa | Evidência |
| --- | --- | --- |
| 1 | Cadastro realizado | Cliente autenticado |
| 2 | WhatsApp conectado | Conta ativa com `MTA_Status=conectado` |
| 3 | Configurar pagamento Meta | `MTA_PagamentoMetaStatus=confirmado_cliente` na conta |
| 4 | Disponibilizar primeiro template | Template ativo, com ID Meta, da mesma conta |
| 5 | Template aprovado | Pelo menos um template local ativo `APPROVED` da mesma conta |
| 6 | Primeira mensagem entregue | Entrega ou leitura confirmada com ID de mensagem na mesma conta |

Essas são as etapas principais para quem começa. Um template já aprovado satisfaz as etapas 4 e 5; não se exige criar outro. A entrega comprovada encerra a ativação mesmo sem lista, contatos importados ou campanha. A retirada posterior de um template não apaga uma entrega histórica. Pendências atuais de conexão, pagamento ou acesso podem apresentar recuperação, preservando a conquista.

Contatos, listas e campanhas são opcionais. Durante análise de template, contatos podem ser organizados se o acesso operacional permitir. Após a ativação, contatos e campanhas aparecem como evolução opcional.

## Critério de ativação e primeiro envio

São consideradas mensagens de saída da API em `conversa_mensagens`/`conversas`, registros em `disparos` e itens de `disparo_manual_itens`/`disparo_manual_lotes`, sempre vinculados ao cliente e à conta contextual. Exige-se ID de mensagem não vazio e estado `delivered`/`entregue` ou `read`/`lido` (leitura implica entrega). Mensagens recebidas, histórico importado e eco do Business App não ativam. Origem nula é aceita para compatibilidade com registros legados de saída.

A evidência histórica de entrega é consultada separadamente da tentativa mais recente. Uma falha posterior não desfaz a ativação. Para a tentativa atual, a data da mensagem ou do item manual determina a mais recente; o estado confirmado em conversa atualiza a interpretação do mesmo ID manual. Sem fonte datada, `disparos` é fallback legado, priorizando falha conhecida sobre aceitação antiga.

| Situação | Comportamento |
| --- | --- |
| Nenhuma tentativa e template aprovado | “Enviar minha primeira mensagem”, para `disparo` |
| Na fila/processando | Acompanhar resultado antes de repetir |
| Aceita pela Meta com ID | Aguardar confirmação; não concluir |
| `sent` | Aguardar entrega; não concluir |
| `failed` | Ver resultado no histórico manual ou Conversas e tentar novamente |
| Falha somente no legado | Informar ausência de histórico detalhado e oferecer novo Disparo Manual |
| `delivered`/`read` com ID | “Primeira mensagem entregue! ✓”; guia compacto |

O caminho inicial permite digitar um número diretamente no Disparo Manual. Não requer importação, lista ou campanha. As validações de envio existentes continuam soberanas. O Dashboard não dispara, não reprocessa e não consulta a Meta ao carregar. O botão para atualizar o Dashboard apenas relê o estado persistido.

## Pré-trial e acesso

O cálculo de acesso e avaliação continua no `Auth`; esta branch não reproduz datas, cotas, início ou reinício de trial. Antes da conexão, os textos são:

> Seu período de avaliação ainda não começou.
>
> Os 7 dias de avaliação começam quando a conexão do seu número do WhatsApp for concluída. A avaliação permite até 200 mensagens, conforme as regras atuais da plataforma.

O CTA inicial é “Conectar meu WhatsApp”. Conexão parcial não representa início de avaliação. Se o número constar conectado, mas o acesso ainda estiver em pré-trial, o guia pede conferência da liberação, sem iniciá-la. Dias e mensagens restantes da avaliação ativa vêm de `Auth::dadosAvaliacaoCliente(false)`.

Acesso operacional bloqueado fora do pré-trial direciona ao Financeiro. Perfis sem permissão de gerenciamento recebem orientação para procurar o responsável. Não são oferecidas etapas operacionais indisponíveis apenas porque existe uma evidência anterior.

## Conexão

`pendente_registro`: “Falta concluir a conexão” / “Concluir registro”. `erro_registro`: conferir PIN de seis dígitos e tentar novamente. `requer_acao`: conferir a pendência. Uma conta desconectada oferece reconexão. Todos levam à configuração Meta existente.

No fluxo de coexistência, pendências orientam conferir as instruções da Meta; não sugerem usar o registro por PIN do Disparador. O Dashboard não executa registro, Embedded Signup nem sincronização.

## Pagamento Meta

A mensalidade do Disparador.net, paga à RL2 Net, é explicada separadamente das tarifas de mensagens cobradas pela Meta. O CTA externo abre o gerenciador Meta. “Já configurei” reaproveita o POST `configuracao/confirmarPagamentoMeta`, com CSRF e ID da conta contextual, preservando a validação de propriedade do endpoint existente.

A confirmação é uma declaração do cliente. O texto informa explicitamente que o Disparador não verifica tecnicamente a forma de pagamento. Carregar o Dashboard não confirma pagamento nem cria endpoint ou regra financeira.

## Templates

`CREATED` não é inventado como status persistido: a disponibilidade é inferida da existência de template ativo com ID Meta. `PENDING` mostra análise pela Meta e permite abrir a tela de templates para atualizar a situação. `APPROVED` habilita a orientação de primeiro envio. `REJECTED` direciona a consultar o motivo e preparar outro template. Estados diferentes, como pausado ou desativado, indicam indisponibilidade e revisão.

Templates inativos ou de outra conta não cumprem a etapa. Um aprovado preexistente prevalece sobre outros pendentes/rejeitados. A leitura é local e pode estar desatualizada; não é uma garantia de aptidão atual na Graph API. A tela existente permanece responsável pela sincronização explícita, e o envio mantém suas verificações operacionais.

## Apresentação e suporte provisório

Durante o onboarding, próximo passo e progresso ocupam o topo. Plano, consumo, excedentes, qualidade, limite de mensagens, última consulta, contadores e últimas campanhas ficam em “Informações da conta e painel operacional”, inicialmente recolhido. Após entrega, o painel operacional volta à apresentação normal. Qualidade usa rótulos legíveis; códigos como `UNKNOWN` e “Nunca” deixam de dominar a orientação inicial.

O suporte reaproveita `ConfiguracaoSite::obterConfiguracaoWhatsappSite()` e a normalização institucional do telefone. O link WhatsApp inclui a etapa atual e convida a combinar horário, sem promessa de resposta imediata. Nenhuma mensagem foi enviada durante a validação. Sem número institucional ativo, o guia não inventa telefone ou agenda.

Etapa 2: solicitação estruturada de atendimento, agendamento, persistência, acompanhamento e notificações. Nada disso foi criado nesta branch.

## Validação realizada

PHP 8.4.14 no Windows; SQLite habilitado nos testes por `-d extension=pdo_sqlite`, assertions por `-d zend.assertions=1 -d assert.exception=1`.

| Teste | Resultado |
| --- | --- |
| OnboardingGuidedDashboardTest | OK, 46 cenários incluindo A–Q e isolamento de conta/cliente |
| OnboardingGuidedDashboardViewTest | OK, textos, CTAs, CSRF, escaping, opcionais e painel secundário |
| DashboardReadOnlyTest | OK, 42 SELECTs acumulados em cinco chamadas do controller isolado; sem escrita de domínio |
| DashboardSuspendedClientTest | OK, cliente suspenso com Auth/política/models/layout reais; CTA Financeiro, uma avaliação, sent não vira entrega, dados preservados e nenhuma chamada ao transporte externo |
| OnboardingReadModelMysqlTest | OK, 13 SELECTs em tabelas temporárias MySQL |
| OnboardingChecklistStaticTest | OK |
| TrialAccessTest | OK |
| PreTrialFirstMetaConnectionTest | OK |
| MetaPaymentStatusTest | OK no MySQL local |
| FinanceiroAccessPolicyServiceTest | OK, regras e observabilidade existentes preservadas |
| MetaSendEligibilityTest | OK |
| MetaStatusWebhookServiceTest | OK |
| MetaAdminStatusSyncTest | OK |
| MetaCoexistenceEligibilityTest | OK |
| EmbeddedSignupRegisterCompletionTest | OK |
| EmbeddedSignupFlowServiceTest | OK |
| TemplateVariableValidationTest | OK |
| TemplateAdminNovaConversaTest | OK |
| WorkerPersistenceStaticTest | OK |
| WorkerRetryPolicyServiceTest | OK |
| MensagemStatusServiceTest | OK |
| ConversaStatusInterfaceTest | Falha preexistente na expectativa de container de metadados; teste e arquivos envolvidos idênticos a origin/main |
| WorkerDaemonRunnerTest | Falha preexistente neste ambiente: funções pcntl indisponíveis no PHP Windows; runner e teste inalterados |

Inspeção visual realizada em HTML renderizado com fixtures isoladas: pré-trial e sucesso no desktop, pré-trial e pagamento em viewport de celular, sem excesso de largura horizontal. Não foi executado envio real, confirmação real de pagamento ou onboarding na Meta. Essa inspeção não substitui um teste integrado autenticado com a Meta.

## Acompanhamento de performance

O ReadModel executa uma consulta sem conta, quatro com tentativa datada e cinco quando precisa do fallback legado. Os 42 SELECTs do teste isolado são acumulados em cinco chamadas, não representam uma abertura completa com Auth e layout. A quantidade de consultas do request varia com as condições de acesso e configuração.

O EXPLAIN da revisão identificou ordenação temporária na busca da mensagem recente e possível varredura de `disparos` na inferência de entrega. Avaliar latência e planos com volume representativo em trabalho futuro; esta correção não cria índices nem modifica essas consultas. A limitação histórica de contas inativadas/excluídas permanece documentada e não exige nova persistência nesta branch.

## Fora do escopo e revisão

Não houve mudanças no algoritmo operacional de envio, worker, webhook, registro, coexistência, elegibilidade, cálculo financeiro ou início de avaliação. Não foram corrigidos problemas separados de paginação/sincronização de templates, associação/deduplicação de mensagens, campanhas ou automação de suporte. Não foram criadas migrações, tabelas ou novos marcos persistidos.

Branch `feat/onboarding-guided-dashboard`, criada após atualizar `main` por fast-forward de `origin/main`. Entrega para revisão pré-commit, sem commit, push, merge commit ou PR.
