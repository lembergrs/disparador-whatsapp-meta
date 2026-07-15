# NFS-e — Etapa 2: Integração HTTP e emissão manual controlada

Data: 2026-07-15

## 1. Escopo implementado

Esta etapa implementa a comunicação HTTP real com a API RL2 NFS-e existente em `https://api.disparador.net`, mas restringe o uso a emissão manual e controlada por administrador. Não há integração automática com webhook Asaas, confirmação manual de pagamento, Worker contínuo, retry automático, download pelo cliente, e-mail, cancelamento automático, suporte a CPF ou emissão em lote.

## 2. Arquitetura

```
Administrador
  -> NfseController
  -> NfseEmissionService
  -> NfseAptidaoFiscalService
  -> NfseDpsSequenciaService
  -> NfsePayloadBuilder
  -> NfseApiClient
  -> API RL2 NFS-e
  -> NfseApiResponseMapper
  -> NfseEmissao
  -> View administrativa
```

Responsabilidades:

- `NfseController`: autenticação/autorização administrativa, validação básica de IDs, POST para ações mutáveis e renderização segura.
- `NfseEmissionService`: orquestra a emissão manual sem manter transação aberta durante HTTP.
- `NfsePayloadBuilder`: monta payload compatível com `POST /acoes/GeraDps.php`, carrega certificado apenas em memória e valida dados fiscais mínimos.
- `NfseApiClient`: executa comunicação cURL/fake transport com endpoints reais, headers, timeouts, status HTTP, `Content-Type`, `X-Request-Id`, duração e erro de transporte.
- `NfseApiResponseMapper`: converte respostas externas para arrays internos estáveis e classifica erros.
- `NfseEmissao`: persiste estado local, sucesso, erro e caminhos privados de XML/PDF.
- `NfseSanitizer`: remove segredos, tokens, certificado, senha, Base64/PFX e caminhos sensíveis de mensagens/arrays.

## 3. Contrato usado

Endpoints implementados no client:

| Operação | Endpoint | Método | Observação |
|---|---|---|---|
| Emissão | `/acoes/GeraDps.php` | POST | Usado pela tela manual |
| PDF | `/acoes/ConsultaDanfse.php` | POST | Ação administrativa explícita |
| XML | `/acoes/ConsultaNfseChave.php` | POST | Suporte técnico preparado |
| DPS | `/acoes/ConsultaDpsChave.php` | POST | Exige `chaveDps`; não usa `numDPS` |
| Eventos | `/acoes/ConsultaNfseEventos.php` | POST | Suporte técnico preparado |
| Cancelamento | `/acoes/CancelaNfse.php` | POST | Método técnico, sem cancelamento automático |

Todos usam `Authorization: Bearer <API_AUTH_TOKEN>` e `Content-Type: application/json`. `cert` e `senhaCert` são enviados apenas no payload em memória.

## 4. Fluxo manual

1. Administrador acessa `NFS-e`.
2. Seleciona cliente e cobrança.
3. Controller valida CSRF e permissão administrativa.
4. `NfseEmissionService` localiza cliente/cobrança.
5. Cria ou busca emissão idempotente por cobrança.
6. Bloqueia status incompatíveis: `emitida`, `cancelada`, `processando`, `reconciliacao_pendente`, `erro_definitivo`.
7. Valida aptidão fiscal PJ.
8. Se não apto, mantém `pendente_dados`, não reserva `numDPS` e não chama API.
9. Se apto, reserva `numDPS` pelo contexto prestador/ambiente/série.
10. Atualiza status para `processando` de forma condicionada.
11. Monta payload fiscal.
12. Chama `POST /acoes/GeraDps.php`.
13. Mapeia resposta.
14. Persiste sucesso ou erro.
15. Exibe resultado seguro na tela administrativa.

## 5. Payload de emissão

O payload produzido segue o contrato real:

- `cert`: PFX em Base64 gerado em memória.
- `senhaCert`: senha em memória.
- `dadosNota.numDPS`: sequência local reservada.
- `dadosNota.dataNota`: competência/data fiscal em `AAAA-MM-DD`.
- `dadosNota.localEmissao`: IBGE de emissão configurado.
- `dadosNota.prestador.CNPJ`: CNPJ do prestador configurado.
- `dadosNota.prestador.IM`: opcional quando configurado.
- `dadosNota.prestador.optSimplesNacional`: configuração fiscal do prestador.
- `dadosNota.tomador`: dados fiscais PJ do cliente.
- `dadosNota.descServico`: descrição fiscal padronizada.
- `dadosNota.valorNota`: valor positivo da cobrança/emissão.

## 6. Persistência

Em sucesso:

- `NFE_Status = emitida`;
- `NFE_RequestIdEmissao`;
- `NFE_IdDps`;
- `NFE_ChaveDps`, somente quando retornada;
- `NFE_ChaveAcesso`;
- `NFE_DataEmissao`;
- tentativa incrementada;
- último erro limpo;
- retorno sanitizado.

XML retornado em `nfseXmlGZipB64`, quando presente, é decodificado, descompactado, salvo em storage privado e associado por caminho relativo e hash SHA-256. Se o XML falhar após sucesso remoto, a nota não é reenviada automaticamente.

## 7. PDF/XML

PDF:

- consultado por `POST /acoes/ConsultaDanfse.php` com `idNota`;
- requer `Content-Type: application/pdf` e conteúdo iniciando com `%PDF`;
- salvo em `storage/nfse/pdf/` com nome não previsível;
- path relativo e hash são persistidos.

XML:

- suporte técnico no mapper/client para `POST /acoes/ConsultaNfseChave.php`;
- exige conteúdo XML válido e não aceita HTML;
- storage privado em `storage/nfse/xml/`.

## 8. Classificação de erros

- Definitivo/configuração: HTTP 400, 401, 405, dados fiscais inválidos, cliente não apto, certificado/configuração ausente.
- Temporário: HTTP 502, HTTP 5xx recuperável, falha de transporte antes do envio.
- Incerto: timeout, conexão encerrada sem resposta, resposta ilegível após possível processamento.

Erro incerto gera `reconciliacao_pendente` e não dispara reenvio automático de `GeraDps.php`.

## 9. Segurança

Nunca persistir ou logar:

- `API_AUTH_TOKEN`;
- `Authorization`;
- `Bearer`;
- `cert`;
- `senhaCert`;
- PFX/Base64;
- XML integral;
- PDF;
- payload fiscal completo;
- stack trace;
- caminho absoluto interno.

Logs de NFS-e guardam somente metadados: timestamp, IDs locais, operação, requestId, status HTTP, duração, status local e código de erro seguro.

## 10. Configuração

Usa as variáveis já criadas na Etapa 1:

- `NFSE_API_BASE_URL`;
- `NFSE_API_AUTH_TOKEN`;
- `NFSE_PRESTADOR_CNPJ`;
- `NFSE_PRESTADOR_IM`;
- `NFSE_PRESTADOR_OP_SIMPLES`;
- `NFSE_LOCAL_EMISSAO_IBGE`;
- `NFSE_DPS_SERIE`;
- `NFSE_CERT_PATH`;
- `NFSE_CERT_PASSWORD`;
- `NFSE_CONNECT_TIMEOUT`;
- `NFSE_REQUEST_TIMEOUT`.

## 11. Roteiro de teste controlado pós-deploy

1. Configurar token, certificado e dados fiscais reais no ambiente.
2. Selecionar cliente PJ de teste com CNPJ/endereço/IBGE válidos.
3. Selecionar cobrança de teste com valor correto.
4. Acessar Administração → NFS-e.
5. Confirmar ação manual.
6. Verificar `requestId`, `numDPS`, `idDps`, `chaveAcesso` e status local.
7. Conferir XML privado, quando retornado.
8. Consultar PDF por ação administrativa explícita.
9. Confirmar que não houve segunda emissão para a mesma cobrança.

## 12. Limitações

- Não há emissão automática após pagamento.
- Não há Worker/retry automático.
- Não há download pelo cliente.
- Não há e-mail.
- Não há suporte a CPF.
- Não há cancelamento automático.
- Não há reconciliação automática.
- Não há múltiplos prestadores.

## 13. Rollback

Rollback de código: reverter o commit da Etapa 2. Como não há migration complementar nesta etapa, não há rollback de banco novo. Arquivos XML/PDF reais eventualmente gerados em teste controlado devem ser tratados operacionalmente conforme política fiscal/contábil, nunca apagados sem validação fiscal.
