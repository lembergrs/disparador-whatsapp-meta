# NFS-e — Auditoria da Etapa 1: Fundação de dados e cadastro fiscal

Data: 2026-07-15
Base auditada: `a0de4a3 feat: criar fundacao de dados para nfse`
Escopo: fundação local de dados, idempotência, sequência `numDPS`, cadastro fiscal, configuração segura, models/services e testes sem rede.

## Resultado geral

A fundação local foi mantida sem chamada à API RL2 NFS-e e sem integração com webhook Asaas, confirmação manual, Worker, download, e-mail ou emissão real. Foram encontrados problemas objetivos de compatibilidade, idempotência contextual, transições de status, sanitização e cobertura de testes. As correções foram aplicadas nesta auditoria.

## Problemas críticos

Nenhum problema crítico permaneceu após a auditoria.

## Problemas altos

### Alto 1 — `NFE_NumDps` único globalmente

- Arquivo: `database/migrations/20260715_create_nfse_foundation.sql`.
- Comportamento: `NFE_NumDps` estava único globalmente, enquanto a estratégia de sequência é separada por prestador, ambiente e série.
- Risco: colisão indevida entre ambientes, séries ou prestadores; uma DPS válida em outro contexto poderia ser bloqueada localmente.
- Correção realizada: adicionados `NFE_PrestadorCnpj` e `NFE_Ambiente` em `nfse_emissoes` e alterada a restrição para `uk_nfse_numdps_contexto (NFE_PrestadorCnpj, NFE_Ambiente, NFE_Serie, NFE_NumDps)`.

### Alto 2 — tipos de `CLI_ID`/`COB_ID` incompatíveis com padrão observado

- Arquivo: `database/migrations/20260715_create_nfse_foundation.sql`.
- Comportamento: a migration usava `BIGINT UNSIGNED` para `CLI_ID` e `COB_ID`, mas migrations existentes do projeto usam `INT` para `CLI_ID` em tabelas relacionadas e os models tratam os identificadores locais como inteiros simples.
- Risco: FKs futuras ficariam incompatíveis com tabelas reais; mesmo sem FK nesta etapa, o modelo documental ficaria divergente do padrão do banco.
- Correção realizada: `CLI_ID` e `COB_ID` em `nfse_emissoes` foram ajustados para `INT`. A migration continua sem FKs nesta etapa para não criar constraint incompatível antes de validar o schema completo de produção; os índices locais preservam a consulta e a futura FK deve ser adicionada somente após confirmação do tipo real.

### Alto 3 — exceção de banco poderia ser confundida com duplicidade

- Arquivo: `app/Models/NfseEmissao.php`.
- Classe/método: `NfseEmissao::criarOuBuscarPorCobranca()`.
- Comportamento: `PDOException` era capturada e, se a busca por cobrança retornasse algo, o método poderia esconder erro não relacionado a duplicate key.
- Risco: falhas reais de banco poderiam ser mascaradas, dificultando auditoria e operação.
- Correção realizada: o método agora só trata como duplicidade erros com SQLSTATE `23000` ou driver code `1062`; demais erros são propagados.

### Alto 4 — sanitização insuficiente para Bearer/Authorization

- Arquivo: `app/Models/NfseEmissao.php`.
- Classe/método: `NfseEmissao::sanitizarMensagem()`.
- Comportamento: a sanitização removia termos, mas podia deixar parte do segredo após espaços, por exemplo em `Authorization: Bearer segredo`.
- Risco: vazamento de token/caminho sensível em mensagem de erro sanitizada.
- Correção realizada: adicionados padrões específicos para `Authorization: Bearer`, `Bearer`, chaves sensíveis e caminhos com `cert/pfx/nfse`.

## Problemas médios

### Médio 1 — transições de status não eram restringidas

- Arquivo: `app/Models/NfseEmissao.php`.
- Classe/método: `NfseEmissao::atualizarStatus()`.
- Comportamento: qualquer status conhecido poderia ser aplicado sem considerar o status atual.
- Risco: `emitida` poderia voltar para `pendente`, `cancelada` poderia ser reprocessada, ou `reconciliacao_pendente` poderia sofrer retry cego em uso futuro.
- Correção realizada: adicionadas `transicoesPermitidas()`, `transicaoPermitida()` e parâmetro opcional de status atual esperado em `atualizarStatus()`.

### Médio 2 — contexto fiscal pouco validado na sequência DPS

- Arquivo: `app/Services/NfseDpsSequenciaService.php`.
- Classe/método: `NfseDpsSequenciaService::reservar()`.
- Comportamento: ambiente e CNPJ do prestador não eram validados suficientemente; overflow não era considerado.
- Risco: criação de sequências em ambientes inválidos ou contexto fiscal incorreto.
- Correção realizada: validação de CNPJ com 14 dígitos, lista de ambientes permitidos, rejeição de sequência <= 0 e verificação de limite operacional.

### Médio 3 — normalização de configuração incompleta

- Arquivo: `app/Services/NfseConfigService.php`.
- Comportamento: série e ambiente eram retornados sem normalização completa e timeouts podiam ser zero/negativos nos dados públicos.
- Risco: contexto fiscal ou diagnóstico operacional inconsistente.
- Correção realizada: série passa a conter apenas dígitos, ambiente é limitado a valores conhecidos e timeouts são no mínimo 1.

### Médio 4 — testes estáticos insuficientes

- Arquivos: `tests/NfseFoundationStaticTest.php`, `tests/NfseAptidaoFiscalServiceTest.php`.
- Comportamento: os testes iniciais cobriam presença de strings, mas pouco comportamento de models/serviços.
- Risco: falso positivo em idempotência, sequência e transições.
- Correção realizada: adicionados testes comportamentais com fakes para `NfseEmissao` e `NfseDpsSequenciaService`, além de novos cenários de CNPJ mascarado/inválido e IBGE inválido.

## Problemas baixos

### Baixo 1 — documentação da implementação precisava refletir as correções

- Arquivo: `docs/NFSE_LEVANTAMENTO_TECNICO.md`.
- Comportamento: a seção de implementação ainda descrevia a primeira fundação antes da auditoria.
- Risco: divergência entre implementação e documentação.
- Correção realizada: atualização da seção “Implementação — Etapa 1” para registrar índice composto de `numDPS`, transições de status, sanitização e decisões finais.

## Confirmações de escopo

- Não foi implementada chamada HTTP para `api.disparador.net`.
- Não foi implementada emissão, consulta PDF/XML, consulta de eventos ou cancelamento.
- Não houve integração com webhook Asaas, confirmação manual, Worker ou retry operacional.
- Não foi aplicado SQL em banco real.
- Não foi adicionado suporte a CPF para emissão automática.
- Não há armazenamento de token, certificado PFX/Base64, senha de certificado ou Authorization no banco.

## Riscos restantes

- A migration ainda deve ser validada em ambiente descartável MariaDB 11.x antes de qualquer deploy, pois o repositório não contém schema completo de produção.
- O cadastro fiscal cria campos específicos `CLI_NFSe_*`; a duplicidade com campos já existentes é intencional como snapshot/dados fiscais, mas exige governança operacional para evitar divergência.
- A reserva de `numDPS` pode gerar lacunas se uma emissão apta for reservada e posteriormente abortada; a decisão documentada é aceitar lacunas locais e não usá-las como reconciliação remota.
- A futura implementação de emissão deve manter a decisão de não reservar `numDPS` para registros `pendente_dados`.

## Testes executados na auditoria

- `php -l` individual nos PHP criados/alterados.
- Testes novos de fundação, aptidão fiscal, model de emissão e sequência DPS.
- Testes de regressão do Worker solicitados.
- `git diff --check`.
- Auditoria do diff por termos sensíveis: `API_AUTH_TOKEN`, `Authorization`, `Bearer`, `senhaCert`, `CERT_PASSWORD`, `PFX`, `base64`.
