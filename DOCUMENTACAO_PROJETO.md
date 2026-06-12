# Documentação Técnica do Projeto `disparador-whatsapp-meta`

## 1. Visão geral

O `disparador-whatsapp-meta` é uma aplicação PHP para gerenciamento de clientes, contas Meta/WhatsApp Business, templates, contatos, listas, campanhas, disparos, conversas e financeiro.

O projeto utiliza um padrão MVC manual, sem framework full-stack. A aplicação é organizada em Controllers, Models, Views, Core e Services, com roteamento próprio baseado no parâmetro `url`.

Estrutura principal:

```text
app/
  Controllers/
  Core/
  Models/
  Services/
  Views/
config/
public/
worker.php
index.php
composer.json
.htaccess
```

## 2. Padrão MVC utilizado

### 2.1 Controllers

Os Controllers ficam em `app/Controllers` e usam o namespace `Controllers`.

Em geral, eles são responsáveis por:

- validar autenticação e autorização;
- ler dados de `$_GET`, `$_POST` e `$_FILES`;
- chamar Models e Services;
- montar dados para as Views;
- redirecionar o fluxo após ações de escrita;
- definir mensagens flash de sucesso ou erro.

Os Controllers normalmente estendem `Core\Controller`, que fornece os métodos base `view()` e `redirect()`.

### 2.2 Models

Os Models ficam em `app/Models` e usam o namespace `Models`.

Eles concentram a maior parte das consultas SQL, usando PDO por meio de `Core\Database::getInstance()`.

O padrão mais comum é um Model por entidade principal do banco, com métodos como:

- `listar`;
- `buscar`;
- `salvar`;
- `atualizar`;
- `inativar`;
- métodos específicos da regra de negócio.

### 2.3 Views

As Views ficam em `app/Views` e são arquivos PHP com HTML, Bootstrap/AdminLTE, jQuery, DataTables e scripts inline em algumas telas.

A área autenticada usa o layout principal em `app/Views/layouts/master.php`.

Algumas telas públicas, como login e páginas do site, são renderizadas sem o layout master.

### 2.4 Core

A pasta `app/Core` contém classes estruturais da aplicação:

- `Router`: resolve Controller e método a partir da URL;
- `Controller`: base para renderização de Views e redirecionamentos;
- `Database`: singleton de conexão PDO;
- `Auth`: controle básico de autenticação/autorização;
- `Session`: helpers para sessão e mensagens flash;
- `Upload` e `Spreadsheet`: suporte a importação de arquivos.

### 2.5 Services

A pasta `app/Services` concentra integrações e regras de negócio que não pertencem diretamente a um Model.

O principal serviço identificado é `MetaService`, usado para comunicação com a API da Meta/WhatsApp Business.

Também existem serviços voltados para financeiro/planos e integração bancária.

## 3. Principais Controllers

### 3.1 `SiteController`

Responsável por páginas públicas e cadastro inicial de clientes.

Principais responsabilidades:

- página inicial pública;
- tela de cadastro;
- gravação de cliente e usuário inicial;
- política de privacidade;
- termos de uso.

O cadastro público cria registros nas tabelas de clientes e usuários com status inicial pendente/inativo, aguardando aprovação.

### 3.2 `LoginController`

Responsável por autenticação.

Principais responsabilidades:

- exibir tela de login;
- validar reCAPTCHA;
- autenticar usuário na tabela `usuarios`;
- verificar senha com `password_verify`;
- criar `$_SESSION['usuario']`;
- encerrar sessão no logout.

A sessão armazena dados como ID do usuário, nome, nível e cliente associado.

### 3.3 `DashboardController`

Responsável pela tela inicial após login.

Para administradores, exibe contadores globais, como clientes, contas Meta, templates, contatos, campanhas e conversas.

Para clientes, exibe informações filtradas por cliente, consumo mensal, excedentes e dados do plano.

### 3.4 `ClienteController`

Controller administrativo para gerenciamento de clientes.

Principais responsabilidades:

- listar clientes por status;
- cadastrar clientes;
- atualizar clientes;
- criar/atualizar usuário vinculado ao cliente;
- inativar clientes;
- reativar clientes;
- aprovar cadastros pendentes.

O acesso é restrito a usuários administradores.

### 3.5 `MetaContaController`

Controller administrativo para gerenciamento de contas Meta/WhatsApp.

Principais responsabilidades:

- listar contas Meta;
- cadastrar conta Meta;
- atualizar conta Meta;
- inativar conta Meta;
- testar conexão com a Meta;
- sincronizar/atualizar status.

O acesso é restrito a administradores.

### 3.6 `ConfiguracaoController`

Controller para clientes visualizarem os números WhatsApp conectados à sua conta.

O acesso é restrito ao nível `cliente`.

### 3.7 `TemplateController`

Controller de templates Meta.

Principais responsabilidades:

- listar templates do cliente;
- criar templates na Meta;
- sincronizar templates da Meta com o banco local;
- inativar templates.

Utiliza `TemplateMeta`, `MetaConta` e `MetaService`.

### 3.8 `ListaContatoController`

Controller para listas de contatos.

Principais responsabilidades:

- listar listas de contatos;
- visualizar contatos de uma lista;
- editar lista;
- inativar lista;
- adicionar contato à lista;
- remover contato da lista;
- duplicar lista.

Trabalha principalmente com `ListaContato`, `ListaContatoItem` e `Contato`.

### 3.9 `CampanhaController`

Controller responsável por campanhas agendadas.

Principais responsabilidades:

- listar campanhas;
- criar campanha;
- mapear variáveis do template para campos do contato;
- gerar fila de envio;
- visualizar detalhes;
- cancelar campanha;
- reagendar campanha;
- pré-visualizar template;
- enviar teste.

A execução efetiva das campanhas depende do `worker.php`.

### 3.10 `DisparoController`

Controller de disparos diretos/manuais.

Permite selecionar conta Meta, template e números de destino para executar disparos sem necessariamente criar uma campanha agendada tradicional.

### 3.11 `ConversaController`

Controller do módulo de atendimento/conversas.

Principais responsabilidades:

- listar conversas;
- carregar mensagens;
- enviar mensagens;
- atualizar lista via AJAX;
- marcar conversas como não lidas;
- gerenciar etiquetas;
- carregar painel de conversa.

A interface de conversas usa várias chamadas AJAX para atualização parcial da tela.

### 3.12 `FinanceiroController`

Controller financeiro voltado ao cliente.

Principais responsabilidades:

- exibir situação financeira;
- listar planos disponíveis;
- permitir escolha/troca de plano.

### 3.13 `FinanceiroAdminController`

Controller financeiro administrativo.

Principais responsabilidades:

- gerenciar planos;
- gerenciar cobranças;
- marcar cobrança como paga;
- cancelar cobrança;
- alterar plano de cliente;
- suspender ou reativar cliente.

### 3.14 `ImportacaoController`

Controller para importação de contatos por arquivo.

Principais responsabilidades:

- exibir tela de importação;
- receber arquivo enviado;
- ler planilha;
- criar ou selecionar lista;
- criar contatos;
- vincular contatos à lista.

Usa `Core\Upload`, `Core\Spreadsheet`, `Contato`, `ListaContato` e `ListaContatoItem`.

## 4. Principais Models

### 4.1 `Cliente`

Representa clientes da plataforma.

Tabela principal: `clientes`.

Responsabilidades comuns:

- listar clientes;
- buscar cliente por ID;
- salvar cliente;
- atualizar cliente;
- inativar cliente;
- buscar cliente com plano;
- listar informações financeiras de clientes.

### 4.2 `MetaConta`

Representa contas Meta/WhatsApp conectadas.

Tabela principal: `meta_contas`.

Campos relevantes identificados:

- `CLI_ID`;
- `MTA_Nome`;
- `MTA_PhoneNumberId`;
- `MTA_WabaId`;
- `MTA_Token`;
- `MTA_UrlBase`;
- `MTA_NumeroTelefone`;
- `MTA_Status`;
- `MTA_Ativo`.

### 4.3 `TemplateMeta`

Representa templates da Meta armazenados localmente.

Tabela principal: `templates_meta`.

Campos relevantes identificados:

- `MTA_ID`;
- `TMP_MetaId`;
- `TMP_Nome`;
- `TMP_Categoria`;
- `TMP_Idioma`;
- `TMP_Status`;
- `TMP_Componentes`;
- `TMP_DataSync`;
- `TMP_Ativo`.

O campo `TMP_Componentes` armazena os componentes do template em JSON.

### 4.4 `Contato`

Representa contatos dos clientes.

Tabela principal: `contatos`.

Responsabilidades comuns:

- listar contatos por cliente;
- salvar contato;
- verificar se telefone já existe;
- listar IDs de contatos;
- buscar contato por telefone;
- extrair campos JSON cadastrados nos contatos.

### 4.5 `ListaContato`

Representa listas de contatos.

Tabela principal: `listas_contatos`.

Relacionamentos importantes:

- uma lista pertence a um cliente;
- uma lista possui vários contatos via `lista_contatos_itens`;
- uma lista pode estar vinculada a campanhas.

### 4.6 `ListaContatoItem`

Representa a relação entre listas e contatos.

Tabela principal: `lista_contatos_itens`.

Responsabilidades comuns:

- adicionar contato a uma lista;
- remover contato de uma lista;
- listar contatos de uma lista;
- verificar se contato já existe na lista.

### 4.7 `Campanha`

Representa campanhas agendadas.

Tabela principal: `campanhas`.

Campos relevantes identificados:

- `CLI_ID`;
- `TMP_ID`;
- `LST_ID`;
- `CAM_Nome`;
- `CAM_Descricao`;
- `CAM_Status`;
- `CAM_DataAgendamento`;
- `CAM_TotalContatos`;
- `CAM_TotalEnviados`;
- `CAM_TotalErros`;
- `CAM_DataCadastro`.

### 4.8 `CampanhaVariavel`

Representa o mapeamento de variáveis de uma campanha.

Tabela principal: `campanha_variaveis`.

É usada para mapear variáveis do template para campos dos contatos.

### 4.9 `FilaEnvio`

Representa a fila de envio de campanhas.

Tabela principal: `fila_envio`.

Cada item de fila associa uma campanha a um contato.

O processamento da fila é feito pelo `worker.php`.

### 4.10 `Conversa`

Representa conversas e mensagens de atendimento.

Tabelas relacionadas:

- `conversas`;
- `conversa_mensagens`;
- `conversa_etiquetas`;
- `conversa_etiqueta_vinculos`.

Responsabilidades comuns:

- buscar ou criar conversa;
- salvar mensagem;
- listar conversas;
- listar mensagens;
- marcar como lida;
- marcar como não lida;
- listar e salvar etiquetas.

### 4.11 `Plano`

Representa planos comerciais.

Tabela principal: `planos`.

Responsabilidades comuns:

- listar planos ativos;
- buscar plano;
- salvar plano;
- editar plano;
- inativar plano.

### 4.12 `Cobranca`

Representa cobranças.

Tabela principal: `cobrancas`.

Relaciona clientes e planos.

Responsabilidades comuns:

- buscar cobrança pendente por cliente;
- criar cobrança;
- marcar como paga;
- cancelar cobrança;
- listar cobranças.

### 4.13 `ConsumoMensal`

Representa consumo mensal de mensagens por cliente.

Tabela principal: `consumo_mensal`.

O mês é controlado pelo formato `YYYYMM`.

### 4.14 `ExcedenteMensal`

Representa mensagens excedentes ao plano contratado.

Tabela principal: `excedentes_mensais`.

## 5. Principais Views

### 5.1 Layout principal

Arquivo: `app/Views/layouts/master.php`.

É usado pela área autenticada e contém:

- estrutura HTML base;
- navbar;
- sidebar;
- menu por tipo de usuário;
- mensagens flash;
- área de conteúdo;
- scripts globais;
- configuração do SDK da Meta/Facebook.

O menu diferencia usuários `admin` e `cliente`.

Para clientes, algumas opções de menu são redirecionadas ao financeiro caso o status de pagamento não esteja liberado.

### 5.2 Views administrativas

Principais Views administrativas:

- `app/Views/clientes/index.php`;
- `app/Views/meta_contas/index.php`;
- `app/Views/financeiro_admin/index.php`.

Elas são usadas para gerenciar clientes, contas Meta, planos, cobranças e situação financeira dos clientes.

### 5.3 Views do cliente

Principais Views da área do cliente:

- `app/Views/dashboard/index.php`;
- `app/Views/configuracao/meta.php`;
- `app/Views/templates/index.php`;
- `app/Views/listas/index.php`;
- `app/Views/campanhas/index.php`;
- `app/Views/campanhas/detalhes.php`;
- `app/Views/disparos/index.php`;
- `app/Views/conversas/index.php`;
- `app/Views/financeiro/index.php`.

Essas telas concentram a operação principal do sistema.

### 5.4 Views públicas e autenticação

Principais Views públicas:

- `app/Views/site/home.php`;
- `app/Views/site/cadastro.php`;
- `app/Views/site/politica_privacidade.php`;
- `app/Views/site/termos_uso.php`;
- `app/Views/auth/login.php`;
- `app/Views/auth/esqueci_senha.php`.

Essas Views normalmente são renderizadas sem o layout autenticado.

## 6. Funcionamento das rotas

O roteamento é feito pela classe `Core\Router`.

A aplicação usa o parâmetro `url` para decidir qual Controller e qual método executar.

Fluxo geral:

1. O `.htaccess` redireciona URLs que não sejam arquivos ou pastas reais para `index.php?url=...`.
2. O `index.php` inicia sessão, carrega configuração, autoload e instancia o Router.
3. O Router lê `$_GET['url']`.
4. O primeiro segmento da URL define o Controller.
5. O segundo segmento define o método.
6. Se o método não for informado, o padrão é `index`.
7. O Controller é instanciado e o método é executado sem parâmetros formais.

Exemplos:

| URL | Controller | Método |
| --- | --- | --- |
| `/` | `SiteController` | `index` |
| `/login` | `LoginController` | `index` |
| `/login/autenticar` | `LoginController` | `autenticar` |
| `/dashboard` | `DashboardController` | `index` |
| `/cliente/index/inativo` | `ClienteController` | `index` |
| `/template/sincronizar` | `TemplateController` | `sincronizar` |
| `/campanha/criar` | `CampanhaController` | `criar` |
| `/conversa/ajaxMensagens` | `ConversaController` | `ajaxMensagens` |

O Router não passa parâmetros para métodos. Parâmetros adicionais precisam ser lidos de `$_GET`, `$_POST` ou do próprio valor de `$_GET['url']`.

## 7. Organização aparente do banco de dados

A aplicação usa MySQL via PDO.

A conexão é centralizada em `Core\Database`, usando constantes definidas em `config/config.php`.

### 7.1 Acesso e clientes

Tabelas principais:

- `usuarios`;
- `clientes`.

A tabela `usuarios` controla login, senha, nível e vínculo com cliente.

A tabela `clientes` representa os tenants/clientes da plataforma.

Níveis identificados:

- `admin`;
- `cliente`.

### 7.2 Meta e WhatsApp

Tabelas principais:

- `meta_contas`;
- `templates_meta`.

`meta_contas` guarda dados de conexão com a API da Meta.

`templates_meta` guarda templates sincronizados/criados na Meta.

### 7.3 Contatos e listas

Tabelas principais:

- `contatos`;
- `listas_contatos`;
- `lista_contatos_itens`.

Um cliente possui contatos e listas.

Uma lista possui vários contatos por meio da tabela de vínculo `lista_contatos_itens`.

### 7.4 Campanhas e fila

Tabelas principais:

- `campanhas`;
- `campanha_variaveis`;
- `fila_envio`.

Uma campanha pertence a um cliente, usa um template e uma lista.

As variáveis da campanha são mapeadas em `campanha_variaveis`.

A fila de contatos a enviar é armazenada em `fila_envio`.

### 7.5 Conversas

Tabelas principais:

- `conversas`;
- `conversa_mensagens`;
- `conversa_etiquetas`;
- `conversa_etiqueta_vinculos`.

A tabela `conversas` representa o atendimento por número/conta Meta.

A tabela `conversa_mensagens` armazena mensagens enviadas e recebidas.

As etiquetas permitem classificação das conversas.

### 7.6 Financeiro

Tabelas principais:

- `planos`;
- `cobrancas`;
- `consumo_mensal`;
- `excedentes_mensais`.

O financeiro controla plano contratado, cobranças, consumo mensal e mensagens excedentes.

## 8. Fluxo de campanhas

O fluxo de campanhas parece seguir estes passos:

1. Cliente cria campanha selecionando template e lista.
2. Sistema salva a campanha com status inicial de agendada.
3. Sistema salva o mapeamento de variáveis.
4. Sistema gera registros em `fila_envio` para os contatos da lista.
5. O `worker.php` busca campanhas agendadas cuja data já chegou.
6. O worker muda status para `processando`.
7. O worker processa itens pendentes da fila em lote.
8. Para cada contato, monta parâmetros do template.
9. Envia mensagem via `MetaService`.
10. Atualiza item da fila como enviado ou erro.
11. Atualiza totais da campanha.
12. Registra mensagem enviada na conversa.
13. Finaliza campanha quando não há pendências.

Esse fluxo é importante porque novas funcionalidades de campanha precisam considerar processamento assíncrono, status e possibilidade de erro/reprocessamento.

## 9. Fluxo de conversas e webhook

O projeto possui um webhook em `public/webhook/meta.php`.

Fluxo aparente:

1. A Meta chama o webhook.
2. Em requisições GET, o webhook valida o token de verificação.
3. Em requisições POST, o webhook recebe eventos.
4. O payload é gravado em log.
5. A conta Meta é identificada pelo `phone_number_id`.
6. Mensagens recebidas geram ou atualizam conversa.
7. Mensagens recebidas são salvas em `conversa_mensagens`.
8. Eventos de status atualizam o status de mensagens já enviadas.

O módulo de conversas consulta esses dados e atualiza a interface por AJAX.

## 10. Cuidados antes de implementar novas funcionalidades

### 10.1 Não quebrar o roteamento

O Router é simples e depende diretamente do nome do Controller e do método.

Antes de criar nova rota, confirmar se o primeiro segmento da URL gera corretamente o nome da classe.

Exemplo:

```text
/index.php?url=template/sincronizar
```

resolve para:

```text
Controllers\TemplateController::sincronizar()
```

### 10.2 Validar permissões no backend

O menu esconde ou redireciona algumas opções, mas regras críticas não devem depender apenas da interface.

Sempre validar no Controller ou Service:

- se o usuário está logado;
- se é admin ou cliente;
- se o recurso pertence ao cliente logado;
- se o cliente está liberado para usar a funcionalidade.

### 10.3 Cuidar do multi-tenant

A aplicação é multi-cliente por `CLI_ID`.

Toda consulta de cliente deve filtrar pelo `CLI_ID` do usuário logado, exceto em telas administrativas.

Esse cuidado é essencial para evitar vazamento de dados entre clientes.

### 10.4 Padronizar `CLI_ID` e `cliente_id`

A sessão contém tanto `CLI_ID` quanto `cliente_id` em alguns fluxos.

Antes de implementar novas funcionalidades, verificar qual chave está sendo usada no módulo afetado e manter consistência.

Idealmente, o projeto deveria padronizar uma única chave para evitar bugs.

### 10.5 Usar prepared statements

A maior parte do projeto usa PDO com prepared statements.

Novas consultas devem seguir esse padrão, especialmente quando receberem filtros, IDs, datas ou textos do usuário.

### 10.6 Respeitar status e soft delete

Muitas tabelas usam status ou flags de ativo, como:

- `CLI_Ativo`;
- `MTA_Ativo`;
- `TMP_Ativo`;
- `CON_Ativo`;
- `LST_Ativo`;
- `CAM_Status`;
- `FIL_Status`;
- `COB_Status`.

Novas consultas devem considerar esses campos para não exibir ou processar registros inativos/cancelados indevidamente.

### 10.7 Proteger dados sensíveis

O arquivo `config/config.php` contém credenciais e chaves diretamente no código.

Antes de evoluir o projeto, recomenda-se migrar segredos para variáveis de ambiente e rotacionar credenciais expostas.

### 10.8 Ter cautela com logs do webhook

O webhook grava payloads recebidos em arquivo de log.

Esses payloads podem conter dados pessoais, números de telefone e conteúdo de mensagens.

É recomendável revisar exposição pública, política de retenção, privacidade e adequação à LGPD.

### 10.9 Considerar concorrência no worker

O `worker.php` processa campanhas e fila de envio.

Antes de alterar esse fluxo, considerar:

- execução simultânea do worker;
- itens presos em `processando`;
- tentativas de reenvio;
- limites por execução;
- atualização de totais;
- idempotência;
- impacto de falhas da API Meta.

### 10.10 Revisar JavaScript inline nas Views

Algumas telas concentram regras importantes em JavaScript inline dentro das próprias Views.

Antes de alterar comportamento de templates, disparos, campanhas ou conversas, revisar a View inteira e também `public/assets/js/app.js`.

### 10.11 Testar fluxos completos

Por ser um MVC manual, sem testes automatizados aparentes, mudanças devem ser testadas manualmente nos fluxos principais:

- login;
- cadastro/aprovação de cliente;
- cadastro de conta Meta;
- sincronização/criação de template;
- criação de lista e contatos;
- criação de campanha;
- execução do worker;
- recebimento de webhook;
- conversas;
- financeiro.

## 11. Recomendações técnicas futuras

Estas recomendações não são alterações realizadas, apenas pontos para evolução:

- mover credenciais para `.env` ou variáveis de ambiente;
- criar migrations ou documentação do schema do banco;
- padronizar nomes de campos de sessão;
- centralizar validação de autorização por recurso/cliente;
- adicionar proteção CSRF em formulários;
- revisar exposição de logs públicos;
- criar testes básicos para Models/Services críticos;
- separar JavaScript inline em arquivos versionados;
- criar camada de request/response para rotas AJAX;
- melhorar tratamento de erros do Router;
- adicionar controle de concorrência no worker.

## 12. Resumo final

O projeto é um sistema PHP MVC manual, organizado de forma relativamente simples e direta.

A regra de negócio principal gira em torno de clientes, contas Meta, templates, contatos, listas, campanhas, fila de envio, conversas e financeiro.

A arquitetura é funcional, mas exige cuidado especial com autorização, isolamento por cliente, status dos registros, segurança de credenciais, logs sensíveis e processamento assíncrono de campanhas.
