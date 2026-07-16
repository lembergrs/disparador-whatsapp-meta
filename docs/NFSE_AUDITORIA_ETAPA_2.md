# NFS-e — Auditoria da Etapa 2: Integração HTTP e emissão manual

Data: 2026-07-15
Base auditada: `5770c7a feat: integrar emissao manual de nfse`

## Resultado geral

A auditoria encontrou problemas objetivos em concorrência da emissão manual, classificação de timeout, validação de PDF/XML, tratamento de XML após sucesso remoto, critérios de cobrança elegível, validação do certificado, log e cobertura de testes da tela/sanitização. As correções foram aplicadas sem chamada real à API, sem emissão real, sem Worker, sem retry automático, sem webhook, sem suporte a CPF e sem migration.

## Problemas críticos

Nenhum problema crítico permaneceu após a auditoria.

## Problemas altos

### Alto 1 — `numDPS` podia ser reservado antes da posse atômica do processamento

- Arquivo: `app/Services/NfseEmissionService.php`.
- Classe/método: `NfseEmissionService::emitirManual()`.
- Comportamento: a reserva de `numDPS` ocorria antes da transição condicionada para `processando`.
- Risco: dois requests administrativos simultâneos poderiam consumir sequência antes de um deles perder a disputa pelo processamento.
- Correção realizada: a emissão agora valida aptidão, avança para `pendente` quando necessário, tenta assumir `processando` de forma condicionada e só depois reserva `numDPS` se ainda não existir.

### Alto 2 — falha de XML após sucesso remoto podia alterar o status fiscal principal

- Arquivo: `app/Services/NfseEmissionService.php` e `app/Models/NfseEmissao.php`.
- Classe/método: `NfseEmissionService::salvarXmlDaEmissao()`.
- Comportamento: erro de persistência/decodificação de XML após emissão confirmada podia ser registrado como erro da emissão.
- Risco: uma nota já confirmada remotamente poderia ser tratada como emissão incerta, induzindo o operador a reenviar.
- Correção realizada: o sucesso principal é persistido primeiro e falhas de XML passam a registrar pendência de documento por `registrarFalhaDocumento()`, sem descaracterizar a emissão confirmada.

### Alto 3 — tela permitia selecionar cobranças inelegíveis

- Arquivo: `app/Views/nfse/index.php` e `app/Services/NfseEmissionService.php`.
- Comportamento: a tela listava cobranças sem filtrar status/valor, e a regra do servidor não bloqueava explicitamente cobrança não paga.
- Risco: emissão fiscal manual sobre cobrança pendente/cancelada/valor zero.
- Correção realizada: a view filtra cobranças pagas e positivas, e o service valida no servidor `COB_Status = pago` e valor positivo antes de qualquer reserva/chamada.

## Problemas médios

### Médio 1 — classificação de timeout era conservadora demais e pouco documentada

- Arquivo: `app/Services/NfseApiClient.php` e `app/Services/NfseApiResponseMapper.php`.
- Comportamento: timeout era tratado como incerto de forma uniforme, enquanto falhas DNS/conexão/TLS podem ser pré-envio.
- Risco: excesso de reconciliação manual para falhas comprovadamente anteriores ao envio.
- Correção realizada: o client adicionou `failure_stage` e `incerto`; DNS/conexão/TLS são temporários pré-envio, timeout operacional permanece incerto por segurança quando não há evidência suficiente.

### Médio 2 — validação de PDF/XML precisava de limites e tipos mais rígidos

- Arquivo: `app/Services/NfseApiResponseMapper.php` e `app/Services/NfseEmissionService.php`.
- Comportamento: PDF validava assinatura curta e XML não tinha limite operacional explícito.
- Risco: aceitar conteúdo inválido, HTML/proxy ou resposta excessiva.
- Correção realizada: PDF exige `application/pdf`, assinatura `%PDF-`, tamanho mínimo e limite; XML exige limite de tamanho, validação XML e `LIBXML_NONET` quando SimpleXML estiver disponível.

### Médio 3 — certificado precisava de validação mais segura

- Arquivo: `app/Services/NfsePayloadBuilder.php`.
- Comportamento: a checagem de certificado verificava legibilidade, mas não exigia `is_file`, realpath válido e tamanho operacional.
- Risco: leitura indevida de path inválido/diretório ou arquivo grande demais.
- Correção realizada: exige arquivo real, fora de `public/`, legível, não vazio e até 5 MiB antes de converter para Base64 em memória.

### Médio 4 — log dedicado não tinha rotação local simples

- Arquivo: `app/Services/NfseEmissionService.php`.
- Comportamento: `storage/logs/nfse.log` poderia crescer indefinidamente.
- Risco: consumo progressivo de disco em operação manual/testes.
- Correção realizada: rotação local simples ao ultrapassar 10 MiB, preservando log sanitizado e sem payload/XML/PDF.

### Médio 5 — testes não cobriam controller/view/sanitização em profundidade

- Arquivos: `tests/NfseAdminUiStaticTest.php` e `tests/NfseSanitizerTest.php`.
- Comportamento: não havia teste específico para menu/tela/CSRF/escapes nem sanitização recursiva.
- Risco: regressão de autorização visual, POST/CSRF e vazamento de segredo em mensagens.
- Correção realizada: adicionados testes estáticos seguros para UI/admin e teste recursivo do sanitizer.

## Problemas baixos

### Baixo 1 — confirmação da tela precisava deixar claro que a nota é real

- Arquivo: `app/Views/nfse/index.php`.
- Comportamento: confirmação mencionava emissão manual, mas não destacava claramente emissão fiscal real.
- Risco: clique administrativo sem consciência operacional.
- Correção realizada: texto de confirmação forte: “Esta ação emitirá uma NFS-e real no ambiente configurado.”

## Fluxo final de concorrência

1. Validar admin, cliente, cobrança paga, vínculo cliente/cobrança e valor positivo.
2. Criar ou buscar registro local idempotente.
3. Bloquear status `emitida`, `cancelada`, `processando`, `reconciliacao_pendente` e `erro_definitivo`.
4. Validar aptidão fiscal.
5. Se inapto: manter `pendente_dados`, não reservar `numDPS`, não chamar API.
6. Avançar `pendente_dados -> pendente`, quando aplicável.
7. Assumir `processando` por update condicionado ao status esperado.
8. Somente após posse de `processando`, reservar `numDPS` se ausente.
9. Chamar API fora de transação longa.
10. Persistir sucesso/erro com update condicionado.

## Política final de timeout

- Falha DNS, conexão recusada e erro TLS: temporário pré-envio.
- Timeout cURL operacional: incerto quando não houver evidência segura de que a requisição não foi transmitida.
- Resposta remota JSON de erro: definitiva/temporária conforme HTTP e envelope da API.
- Erro incerto não gera reenvio automático e deve seguir para avaliação/reconciliação manual.

## Persistência após sucesso remoto

A prioridade é persistir identificadores principais (`requestId`, `idDps`, `chaveAcesso`, status `emitida`, data e retorno sanitizado). XML/PDF são documentos derivados: falha ao gravar documento registra pendência segura, mas não rebaixa a emissão confirmada para emissão inexistente.

## Tratamento de XML

- Base64 estrito.
- Limite de tamanho para conteúdo compactado/descompactado.
- GZip via `gzdecode`.
- Validação mínima de XML.
- Storage privado `storage/nfse/xml/`.
- Escrita via arquivo temporário, `chmod 0660` e `rename`.
- Hash sobre conteúdo final.

## Tratamento de PDF

- HTTP 200.
- `Content-Type` contendo `application/pdf`.
- Assinatura `%PDF-`.
- Tamanho mínimo e limite de 10 MiB.
- Storage privado `storage/nfse/pdf/`.
- Path relativo controlado e hash SHA-256.

## Autorização e CSRF

O controller usa `Auth::admin()` em todas as ações e `validarCsrfPost()` em ações mutáveis. A view emite por POST com `Csrf::input()`. A rota é compatível com o Router atual por `NfseController` e `url=nfse`.

## Logs e rotação

Logs de NFS-e ficam em `storage/logs/nfse.log`, passam por sanitização e registram somente metadados. A auditoria adicionou rotação simples acima de 10 MiB. Ainda é recomendado configurar rotação operacional no servidor.

## Segurança

A auditoria confirmou que não há segredo real versionado, certificado versionado, senha real, XML/PDF fiscal ou payload sensível em fixtures. Termos sensíveis existentes são nomes de configuração, placeholders fictícios, testes negativos ou padrões de sanitização.

## Riscos restantes

- O primeiro teste real ainda exige credenciais e certificado configurados somente em ambiente controlado.
- A validação em produção deve confirmar permissões reais dos diretórios `storage/nfse/xml` e `storage/nfse/pdf` para o usuário do PHP.
- Não há reconciliação automática; timeout incerto exige operação manual.
- Não há download do cliente, e-mail, Worker, webhook ou suporte a CPF nesta etapa.
- Métodos técnicos de XML/DPS/eventos/cancelamento existem no client/mapper, mas interface operacional completa deve permanecer para etapa posterior.

## Roteiro seguro do primeiro teste real

1. Configurar credenciais reais no ambiente, nunca no Git.
2. Confirmar certificado fora de `public/`, legível e com permissão restrita.
3. Usar cliente PJ de teste com CNPJ/endereço/IBGE válidos.
4. Usar cobrança paga e positiva de teste.
5. Confirmar o ambiente fiscal configurado.
6. Acessar a tela NFS-e como admin.
7. Conferir cliente, cobrança, valor e tomador.
8. Confirmar explicitamente a emissão real.
9. Verificar `requestId`, `idDps`, `chaveAcesso`, XML e PDF.
10. Confirmar que nova tentativa para a mesma cobrança é bloqueada.
