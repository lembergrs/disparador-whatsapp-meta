# Integração NFS-e RL2 para mensalidades do Disparador.net

## Resumo executivo

Esta sprint analisou o financeiro atual do Disparador e propõe uma V1 administrativa para emissão de NFS-e das mensalidades/planos pagos. A V1 deve manter a RL2 Net como única prestadora, usar um único perfil fiscal/certificado e vincular cada NFS-e a uma cobrança do financeiro existente. Nenhuma alteração funcional, migration, integração HTTP ou alteração de infraestrutura foi feita nesta sprint.

A base atual já possui cobrança, assinatura, planos, Asaas, webhook de confirmação de pagamento, status financeiros, consumo mensal e campos cadastrais básicos do cliente. A principal lacuna para NFS-e é a ausência/validação parcial de dados fiscais completos do tomador, principalmente código IBGE municipal e validações fiscais/endereço obrigatórias para emissão.

## Escopo da V1

Inclui, em sprints futuras:

- emissão de NFS-e pela RL2 Net para mensalidades do Disparador.net;
- tomador como cliente do Disparador;
- vínculo obrigatório com uma cobrança `cobrancas.COB_ID`;
- tela administrativa manual antes da automação;
- persistência local do ciclo da NFS-e, status, chaves, XML/DANFSe, erros e tentativas;
- consulta, download e cancelamento administrativo.

Não inclui na V1:

- clientes emitindo notas próprias;
- múltiplos prestadores/certificados;
- alteração imediata do financeiro existente;
- emissão automática antes da validação manual;
- migrations nesta sprint.

## Inventário do financeiro atual

### Controllers

- `FinanceiroController`: área do cliente, lista planos ativos, busca cobrança pendente, consumo mensal, excedente e assinatura atual; cria cobrança ao escolher plano e sincroniza com Asaas.
- `FinanceiroAdminController`: área administrativa, lista planos/cobranças/clientes financeiros; salva/edita/inativa planos; marca cobrança como paga; cancela cobrança; processa vencimentos e recorrência.
- `AsaasController`: webhook do Asaas; valida token; registra evento; localiza cobrança por `COB_ProviderPaymentId`; atualiza status local; marca cliente como pago; ativa assinatura.
- `AssinaturaController`: CRUD/status administrativo de assinaturas.
- `ClienteController` e `ContaController`: cadastro administrativo e dados cadastrais do cliente.

### Models

- `Cobranca`: cria, lista, busca, marca paga/cancelada, atualiza dados de provider, registra eventos de provider e localiza cobrança pelo pagamento externo.
- `Assinatura`: controla assinatura atual/pendente/ativa/vencida/cancelada, ciclo e próxima cobrança.
- `Plano`: planos ativos, valores por ciclo e limites.
- `Cliente`: cadastro, status de pagamento, provider de pagamento, listagem financeira e campos de plano/consumo.
- `ConsumoMensal` e `ExcedenteMensal`: registram e consultam consumo/excedente do mês.

### Views administrativas e do cliente

- `app/Views/financeiro/index.php`: tela do cliente para contratar plano e consultar faturas.
- `app/Views/financeiro_admin/index.php`: tela administrativa de planos, cobranças e clientes financeiros.
- `app/Views/clientes/index.php`: cadastro administrativo de clientes.
- `app/Views/conta/index.php`: edição de dados cadastrais pelo cliente.
- `app/Views/assinaturas/index.php`: gestão administrativa de assinaturas.

### Contratação de plano e criação de cobrança

O cliente escolhe plano em `FinanceiroController::escolherPlano()`. O fluxo valida ciclo, impede cobrança pendente duplicada, valida limite de números do plano, atualiza `CLI_Plano_DR` e `CLI_StatusPagamento = 'pendente'`, cria/atualiza assinatura pendente, cria cobrança local `COB_Status = 'pendente'`, `COB_Forma = 'bolepix'`, `COB_Tipo = 'mensalidade'` quando a coluna existe, e tenta sincronizar a cobrança com Asaas.

A cobrança no Asaas recebe `externalReference = cobranca_[COB_ID]`, descrição `Mensalidade [PLA_Nome]`, valor de `COB_Valor`, vencimento de `COB_DataVencimento`, customer de `CLI_ProviderCustomerId` e retorno com link/pix/ID do provider salvo em `cobrancas`.

### Pagamentos e confirmações

Existem dois caminhos de confirmação:

1. Manual administrativo: `FinanceiroAdminController::marcarPago()` muda cobrança pendente/vencida para `pago`, grava `COB_DataPagamento`, atualiza cliente para `CLI_StatusPagamento = 'pago'`, `CLI_StatusCadastro = 'ativo'`, libera o trial se necessário e ativa a assinatura vinculada ou encontrada para cliente/plano.
2. Webhook Asaas: `AsaasController::webhook()` processa eventos `PAYMENT_RECEIVED` e `PAYMENT_CONFIRMED`, atualiza `COB_Status = 'pago'`, grava `COB_DataPagamento`, marca cliente como pago e ativa assinatura.

Evento recomendado para disparar NFS-e no futuro: a transição idempotente de uma cobrança `mensalidade` para `COB_Status = 'pago'`, preferencialmente após `PAYMENT_RECEIVED`/`PAYMENT_CONFIRMED` do Asaas ou após lançamento manual administrativo. A automação deve verificar se já existe NFS-e autorizada/pendente para o mesmo `COB_ID` antes de emitir.

### Status financeiros identificados

- Cobranças: `pendente`, `pago`, `vencido`, `cancelado`, `erro`.
- Assinaturas: `ativa`, `pendente`, `vencida`, `cancelada`.
- Cliente: `CLI_StatusPagamento` usa pelo menos `pendente` e `pago`; o cadastro usa `CLI_StatusCadastro` como `pendente`, `ativo`, `inativo`.
- Asaas/provider: `COB_ProviderStatus` preserva status externo e status internos como `local_pendente`, `erro_cliente`, `erro_cobranca`.

### Referências externas e webhooks

- Cliente Asaas: `CLI_ProviderPagamento`, `CLI_ProviderCustomerId`, `CLI_DataSincronizacaoProvider` quando existirem.
- Cobrança Asaas: `COB_Provider`, `COB_ProviderCustomerId`, `COB_ProviderPaymentId`, `COB_ProviderStatus`, `COB_ProviderPayload`, `COB_DataSincronizacaoProvider`, links de pagamento e dados Pix quando existirem.
- Eventos de provider: tabela `cobranca_eventos`, com deduplicação por provider/event ID.
- Logs de webhook: `storage/logs/asaas-webhook.log`.

## Inventário de dados fiscais do cliente

| Dado NFS-e | Campo atual provável | Situação | Observação |
| --- | --- | --- | --- |
| CPF/CNPJ | `CLI_CPF_CNPJ` | Existe | Normalizado para dígitos em cadastro; precisa validação fiscal antes da emissão. |
| Nome/razão social | `CLI_RazaoSocial` e `CLI_Nome` | Existe | Para PJ usar razão social; fallback para nome. |
| E-mail | `CLI_Email` | Existe | Já validado para Asaas; exigir formato válido. |
| Telefone | `CLI_Telefone` | Existe | Normalizado para dígitos; validar DDD/tamanho. |
| CEP | `CLI_CEP` | Existe condicional | `ContaController` e `Cliente::atualizarDadosConta()` usam somente se coluna existir. |
| Endereço/logradouro | `CLI_Logradouro` | Existe condicional | Precisa obrigatoriedade no fluxo fiscal. |
| Número | `CLI_Numero` | Existe condicional | Precisa não vazio ou regra fiscal para “S/N”. |
| Complemento | `CLI_Complemento` | Existe condicional | Opcional. |
| Bairro | `CLI_Bairro` | Existe condicional | Precisa obrigatoriedade. |
| Município | `CLI_Cidade` | Existe condicional | Precisa normalização e vínculo IBGE. |
| UF | `CLI_UF` | Existe condicional | Não estava na lista inicial, mas é necessário para endereço. |
| Código IBGE município | Não localizado | Ausente | Deve ser incluído em sprint futura; não criar migration agora. |

Campos que precisam validação/normalização: CPF/CNPJ, e-mail, telefone, CEP, UF, cidade, código IBGE, logradouro, número e bairro. Já existe `DocumentoFiscalValidator` para CPF/CNPJ, mas a emissão deve consolidar validações do tomador em um validador específico de NFS-e.

## Lacunas encontradas

- Não há módulo NFS-e ou tabelas de notas fiscais.
- Não há armazenamento específico para certificado PFX/senha/token RL2 NFS-e.
- Não há controle transacional de `numDPS`.
- Não há campo `CLI_CodigoMunicipioIbge` localizado.
- Campos de endereço existem como colunas opcionais/condicionais; precisa confirmar schema real da produção.
- Não há tela fiscal para revisar tomador antes da emissão.
- Não há rotina para baixar/armazenar XML/DANFSe privados.
- Não há fila/worker financeiro genérico; automação deve ser posterior e idempotente.

## Configuração recomendada da RL2 Net

### Alternativas avaliadas

- `.env`: simples, já adotado para DB/Meta/Asaas, adequado para token, URL, caminho de certificado e parâmetros não volumosos.
- Tabela de configurações: útil para próximo número de DPS e ajustes auditáveis, mas não ideal para senha/token em texto puro.
- Arquivo fora da pasta pública: adequado para certificado PFX e artefatos XML/PDF privados.
- Armazenamento criptografado: recomendado para senha do certificado se for parar no banco; adiciona complexidade.
- Secrets da hospedagem/VPS: melhor para VPS, mas pode não existir na hospedagem compartilhada atual.

### Recomendação V1 para hospedagem compartilhada

- Guardar em `.env` somente valores sensíveis pequenos e caminhos:
  - `NFSE_API_URL=https://api.disparador.net`
  - `NFSE_API_TOKEN=` sem valor versionado
  - `NFSE_PRESTADOR_CNPJ=` sem valor versionado
  - `NFSE_PRESTADOR_SIMPLES_NACIONAL=`
  - `NFSE_MUNICIPIO_EMISSAO_CODIGO_IBGE=`
  - `NFSE_CERT_PFX_PATH=/caminho/privado/rl2.pfx`
  - `NFSE_CERT_PFX_PASSWORD=`
  - `NFSE_AMBIENTE=producao|homologacao`
- Guardar o PFX fora de `public`, preferencialmente em `storage/private/certs/rl2.pfx` ou diretório acima do document root quando a hospedagem permitir.
- Garantir `.htaccess` deny em diretórios privados quando ficarem dentro do projeto.
- Não commitar `.env`, PFX, senha, token, XML ou PDF fiscal.
- Guardar `próximo número da DPS` em tabela transacional própria, não no `.env`.
- Guardar série em configuração de banco ou `.env` se for fixa; recomendação V1: tabela de sequência por ambiente/prestador/série para ficar junto do controle do número.

## Persistência recomendada

### Tabela principal sugerida: `nfse_notas`

Campos mínimos propostos:

- `NFS_ID` PK;
- `CLI_ID` FK lógico;
- `COB_ID` FK lógico/único para evitar duplicidade por cobrança;
- `NFS_Ambiente`;
- `NFS_PrestadorCnpj`;
- `NFS_NumDps`;
- `NFS_Serie`;
- `NFS_IdDps`;
- `NFS_ChaveAcesso`;
- `NFS_Numero`;
- `NFS_Status` (`reservada`, `enviando`, `autorizada`, `erro`, `cancelada`, `consultar_status`, `timeout`);
- `NFS_RequestId`;
- `NFS_Valor`;
- `NFS_Descricao`;
- `NFS_DataCompetencia`;
- `NFS_DataEmissao`;
- `NFS_XmlPath` e/ou `NFS_XmlBase64Compactado`;
- `NFS_DanfsePath`;
- `NFS_Erro`;
- `NFS_CodigoErro`;
- `NFS_DetalhesSanitizados`;
- `NFS_Tentativas`;
- `NFS_DataCancelamento`;
- `NFS_MotivoCancelamento`;
- `NFS_EventoCancelamento`;
- `NFS_CriadoEm`;
- `NFS_AtualizadoEm`.

Índices/constraints futuros:

- único por `COB_ID` para cobrança faturada uma vez;
- único por `NFS_Ambiente`, `NFS_PrestadorCnpj`, `NFS_Serie`, `NFS_NumDps`;
- índice por `NFS_ChaveAcesso`;
- índice por status/data.

### Tabela de eventos sugerida: `nfse_eventos`

Registrar consultas/cancelamentos/retornos importantes:

- `NEV_ID`, `NFS_ID`, `NEV_Tipo`, `NEV_RequestId`, `NEV_StatusAntes`, `NEV_StatusDepois`, `NEV_Codigo`, `NEV_Mensagem`, `NEV_PayloadSanitizado`, `NEV_CriadoEm`.

### Banco vs arquivos privados

No banco:

- metadados, chaves, status, datas, valor, descrição, erro resumido, requestId, caminhos de arquivo e payloads sanitizados curtos.

Em arquivos privados:

- XML completo;
- DANFSe PDF;
- payload bruto só se houver necessidade operacional e com sanitização/controle de acesso.

Caminho sugerido:

- `storage/private/nfse/{ambiente}/{ano}/{mes}/{NFS_ID}/nota.xml.gz`
- `storage/private/nfse/{ambiente}/{ano}/{mes}/{NFS_ID}/danfse.pdf`

## Estratégia de `numDPS`

Não usar `MAX(numDPS) + 1`, pois concorrência, timeout e reenvio podem duplicar números.

### Tabela sugerida: `nfse_dps_sequencias`

- `SEQ_ID` PK;
- `SEQ_Ambiente`;
- `SEQ_PrestadorCnpj`;
- `SEQ_Serie`;
- `SEQ_ProximoNumero`;
- `SEQ_AtualizadoEm`;
- índice único por ambiente/prestador/série.

### Reserva segura em MySQL/MariaDB

1. Abrir transação.
2. Selecionar linha da sequência com `SELECT ... FOR UPDATE` por ambiente/prestador/série.
3. Reservar `SEQ_ProximoNumero` para uma nova linha `nfse_notas` em status `reservada`.
4. Incrementar `SEQ_ProximoNumero = SEQ_ProximoNumero + 1`.
5. Commit.
6. Enviar para API fora da transação longa.
7. Em timeout, deixar nota como `timeout`/`consultar_status` e consultar por `idDps`, chave ou eventos antes de reenviar.
8. Reenvio deve reutilizar a nota reservada quando o envio anterior pode ter chegado à API; nunca reservar outro número cegamente.
9. Criar tela administrativa restrita para ajustar sequência com justificativa/auditoria quando houver necessidade contábil.

## Proposta do `NfseApiService`

Arquivo futuro: `app/Services/NfseApiService.php`.

Responsabilidades:

- encapsular URL base, token Bearer, timeouts e TLS;
- serializar JSON UTF-8;
- lidar com resposta JSON e binária;
- padronizar retorno com `sucesso`, `http_code`, `request_id`, `response`, `erro`, `headers`, `endpoint`;
- nunca expor token/certificado em logs;
- permitir consulta antes de reenviar após timeout.

Métodos sugeridos:

```php
public function emitir(array $payload): array;
public function consultarPorChave(string $chaveAcesso): array;
public function baixarDanfse(string $chaveAcesso): array; // binário PDF/base64 conforme API
public function cancelar(string $chaveAcesso, string $codigoCancelamento, string $motivo): array;
public function consultarEventos(string $chaveAcesso): array;
```

Retorno recomendado:

```php
[
    'sucesso' => true|false,
    'http_code' => 200,
    'request_id' => '...',
    'endpoint' => '/...',
    'response' => [...],
    'binary' => null|string,
    'content_type' => 'application/json|application/pdf|application/xml',
    'erro' => null|string,
    'codigo_erro' => null|string,
]
```

## Fluxo manual administrativo V1

### Telas

1. Lista de cobranças em `financeiroAdmin` com coluna “NFS-e”.
2. Detalhe/modal da cobrança paga com dados do tomador e dados da nota.
3. Tela/modal “Emitir NFS-e” para revisar:
   - cliente;
   - cobrança;
   - plano;
   - valor;
   - competência;
   - descrição;
   - CPF/CNPJ;
   - razão/nome;
   - endereço completo;
   - código IBGE;
   - e-mail/telefone.
4. Tela/modal de consulta/cancelamento da NFS-e.

### Botões e permissões

- `Emitir NFS-e`: somente `Auth::admin()`, visível para cobrança `pago` sem nota autorizada/cancelada.
- `Consultar`: nota com chave/idDps/requestId.
- `Baixar XML`: nota autorizada com arquivo salvo.
- `Baixar DANFSe`: nota autorizada com PDF salvo ou disponível para download.
- `Cancelar`: nota autorizada, exige código/motivo e confirmação.
- `Reprocessar/Consultar timeout`: notas em `erro`, `timeout`, `consultar_status`, com regra anti-duplicidade.

### Estados

- Sem NFS-e;
- Dados fiscais incompletos;
- Pronta para emitir;
- Reservada;
- Enviando;
- Autorizada;
- Erro;
- Timeout/consultar status;
- Cancelada.

## Automação futura

Após validação manual:

- disparar job após confirmação de pagamento no webhook Asaas e após lançamento manual pago;
- preferir fila/worker na VPS; em hospedagem compartilhada, usar cron HTTP/CLI com lote pequeno e trava por banco;
- política de retry: erros transitórios com backoff; erros fiscais não retry automático até correção;
- reconciliação diária de notas `reservada`, `enviando`, `timeout`, `consultar_status` via consulta por chave/idDps/eventos;
- notificar administrador quando emissão falhar;
- bloquear duplicidade com unique `COB_ID` e unique ambiente/prestador/série/numDPS;
- automação só deve emitir cobranças tipo `mensalidade` pagas e com tomador validado.

## Descrição e valor da nota

Fontes atuais:

- valor pago/base: `COB_Valor`; conferir se deve usar valor bruto, líquido, desconto/juros/multa do Asaas ou valor efetivamente recebido;
- plano: `PLA_Nome` via cobrança/plano/assinatura;
- período/competência: derivar de `COB_DataPagamento`, `COB_DataVencimento` ou período da assinatura; precisa definição contábil;
- descontos/juros/multa: não há campos locais explícitos identificados na cobrança; Asaas pode fornecer `value`/`netValue`, mas acréscimos/descontos exigem mapeamento;
- descrição: `Prestação de serviço referente ao plano [PLANO] do Disparador.net, competência [MÊS/ANO].`

Pontos para validação contábil:

- item/serviço e código municipal/LC 116;
- valor tributável quando houver tarifa, desconto, multa ou juros;
- competência correta;
- retenções/ISS/Simples Nacional;
- emissão para CPF vs CNPJ;
- regras de cancelamento e substituição.

## Compatibilidade hospedagem compartilhada e VPS

- Certificado: caminho configurável via `.env`; não hardcode de `/home/...` ou `/var/www/...`.
- Token/senha: `.env` ou variável de ambiente real; nunca banco em texto puro sem criptografia.
- Arquivos: usar `storage/private/nfse` fora de `public`; se a hospedagem expuser o projeto inteiro, proteger com `.htaccess` e downloads via controller autenticado.
- Permissões: diretórios graváveis pelo usuário PHP; não assumir `chown`/systemd.
- HTTPS: validar TLS; timeouts curtos e logs sanitizados.
- Deploy: parâmetros por ambiente em `.env`; sequência por ambiente no banco.
- Migração VPS: mover PFX/arquivos privados e atualizar caminhos; manter URLs/tokens em secrets/variáveis do deploy.

## Tratamento de erros

- Classificar erro em validação local, erro fiscal retornado pela API, timeout, erro HTTP/TLS, erro de armazenamento de arquivo e erro de concorrência.
- Salvar `requestId`, HTTP code, código de erro, mensagem curta e detalhes sanitizados.
- Não salvar token, senha, PFX, payload completo com segredo ou dados sensíveis desnecessários.
- Em timeout, consultar status antes de qualquer reenvio.
- Em erro fiscal corrigível, manter nota/cobrança bloqueada para revisão administrativa.

## Permissões

- V1 manual somente para administradores internos (`Auth::admin()`).
- Cliente não deve emitir/cancelar; no máximo poderá baixar DANFSe própria em fase posterior.
- Cancelamento deve exigir confirmação, motivo, código e auditoria.
- Ajuste de sequência deve ser permissão administrativa reforçada e registrado em log/tabela.

## Arquivos e tabelas afetados em sprints futuras

Arquivos prováveis:

- `config/config.php` para constantes `NFSE_*`;
- `.env.example` para chaves vazias/documentadas;
- `app/Services/NfseApiService.php`;
- `app/Services/NfseEmissaoService.php` ou equivalente para orquestração;
- `app/Models/NfseNota.php`;
- `app/Models/NfseDpsSequencia.php`;
- `app/Controllers/NfseAdminController.php`;
- `app/Views/nfse_admin/*` ou inclusão em `financeiro_admin/index.php`;
- rotas MVC por convenção `index.php?url=nfseAdmin/...`;
- `storage/private/nfse` e `storage/private/certs`.

Tabelas futuras:

- `nfse_notas`;
- `nfse_eventos`;
- `nfse_dps_sequencias`;
- possível `nfse_auditoria` ou uso de eventos para ações administrativas;
- possível campo `CLI_CodigoMunicipioIbge` em `clientes`.

## Plano de implementação em pequenas sprints

1. **Configuração e base fiscal**: adicionar `.env.example`, constantes `NFSE_*`, diretórios privados, Model de configuração/sequência e migrations revisadas.
2. **Dados fiscais do tomador**: adicionar campo IBGE e validações; tela administrativa para completar dados fiscais sem emitir.
3. **Persistência NFS-e**: criar migrations `nfse_notas`, `nfse_eventos`, `nfse_dps_sequencias`; models com reservas transacionais.
4. **Cliente API**: implementar `NfseApiService` com emissão/consulta/DANFSe/cancelamento/eventos e testes controlados.
5. **Fluxo manual admin**: botões/telas em financeiro admin, emissão manual, armazenamento XML/PDF e consulta.
6. **Cancelamento e reconciliação**: cancelar, consultar eventos, tratar timeout e reprocessar consultas.
7. **Automação controlada**: fila/cron após webhook/lancamento manual, retry, notificações e bloqueio duplicidade.
8. **Migração VPS/observabilidade**: secrets, logs, backup de arquivos fiscais, monitoramento e rotina operacional.

## Riscos principais

- Dados do tomador incompletos ou inválidos, especialmente IBGE/endereço.
- Duplicidade de `numDPS` por concorrência ou reenvio após timeout.
- Divergência contábil sobre valor tributável/competência/serviço.
- Armazenamento inadequado de PFX, senha, token, XML/PDF em hospedagem compartilhada.
- Automação emitindo nota para cobrança errada, cancelada, estornada ou já faturada.
- Falta de worker persistente na hospedagem compartilhada.
- Mudança de ambiente/hospedagem com caminhos hardcoded.

## Checklist para primeira emissão pelo Disparador

- [ ] Confirmar dados fiscais da RL2 Net com contabilidade.
- [ ] Confirmar item/serviço, alíquota/ISS/Simples e texto da descrição.
- [ ] Configurar `NFSE_API_URL`, token, ambiente, CNPJ, município e certificado sem versionar segredos.
- [ ] Instalar PFX em diretório privado e testar permissão de leitura.
- [ ] Criar sequência inicial de DPS por ambiente/prestador/série.
- [ ] Validar cadastro fiscal do cliente tomador, incluindo IBGE.
- [ ] Selecionar cobrança `pago` e `mensalidade` sem NFS-e anterior.
- [ ] Emitir manualmente.
- [ ] Salvar chave, número, XML e DANFSe.
- [ ] Consultar por chave e eventos.
- [ ] Validar DANFSe com contabilidade.
- [ ] Registrar procedimento de cancelamento.
