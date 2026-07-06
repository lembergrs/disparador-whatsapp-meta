# Diagnóstico: templates Meta com cabeçalho de mídia

Branch de análise: `analise-templates-midia-meta`.

## Estado atual

### Cadastro e sincronização de templates

- O módulo de templates já possui criação real via API da Meta: `TemplateController::criar()` valida CSRF, valida se a conta Meta pertence ao cliente, instancia `MetaService` e chama `MetaService::criarTemplate($_POST)`. Quando a Meta retorna `id`, o template é salvo localmente por `TemplateMeta::salvarOuAtualizar()`.
- Também existe sincronização/listagem: `TemplateController::sincronizar()` chama `MetaService::buscarTemplates()`, percorre `data`, salva/atualiza cada template localmente e inativa templates ausentes.
- `MetaService::buscarTemplates()` faz `GET /{WABA_ID}/message_templates`.
- `MetaService::criarTemplate()` faz `POST /{WABA_ID}/message_templates` com `name`, `category`, `language` e `components`.

### Templates de texto hoje

- A view `app/Views/templates/index.php` contém modal de novo template com conta Meta, nome, categoria, idioma, header, body, botões e footer.
- No header, apenas `TEXT` está habilitado. `IMAGE`, `VIDEO` e `DOCUMENT` aparecem no select, mas estão `disabled` e marcados como “em breve”.
- `MetaService::criarTemplate()` trata header `TEXT` com `text` e exemplos de variáveis. Para qualquer outro `header_tipo`, ele monta apenas `{ type: "HEADER", format: <tipo> }`, sem `example.header_handle`, sem upload, sem URL e sem arquivo.
- O body é obrigatório e tem normalização de variáveis nomeadas para placeholders numéricos da Meta (`{{1}}`, `{{2}}` etc.). O mapeamento original é preservado em um componente local sintético `VARIABLE_MAPPING` dentro de `TMP_Componentes`.
- Botões suportados hoje: `QUICK_REPLY`, `URL` e `PHONE_NUMBER`.

### Estrutura local de `templates_meta`

Não há migration neste repositório criando `templates_meta`; a estrutura é inferida pelo model e pelas consultas existentes. Os campos usados hoje são:

- `TMP_ID`
- `MTA_ID`
- `TMP_MetaId`
- `TMP_Nome`
- `TMP_Categoria`
- `TMP_Idioma`
- `TMP_Status`
- `TMP_Componentes`
- `TMP_DataSync`
- `TMP_Ativo`

A tabela não possui, pelo código atual, colunas dedicadas para tipo de header ou mídia. As informações de componentes ficam em `TMP_Componentes` como JSON.

### Views e previews

- A listagem/modal de detalhes usa `TMP_Componentes` em JSON para montar preview e variáveis.
- O formulário de criação não tem campo para URL pública, upload local, arquivo de exemplo, `header_handle`, nome de documento ou metadados de mídia.
- O preview do Disparo Manual (`public/assets/js/app.js`) renderiza header somente quando existe `comp.type == 'HEADER' && comp.text`. Headers de mídia sincronizados ou futuros não teriam preview visual.
- A criação de Campanhas passa `TMP_Componentes` no `data-componentes` do select para mapear variáveis e preview. Também não há tratamento de header de mídia.

### Envio atual para Meta

`MetaService::enviarTemplate()` sempre monta o payload com um único componente `body`:

```json
{
  "messaging_product": "whatsapp",
  "to": "...",
  "type": "template",
  "template": {
    "name": "template",
    "language": { "code": "pt_BR" },
    "components": [
      {
        "type": "body",
        "parameters": [
          { "type": "text", "text": "..." }
        ]
      }
    ]
  }
}
```

Consequências:

- Templates com header de mídia estático criado/aprovado na Meta podem até não exigir parâmetro no envio, mas o sistema não registra claramente esse tipo nem mostra preview adequado.
- Templates com header de mídia variável/dinâmico não funcionam, pois o payload não adiciona componente `header` com parâmetro `image`, `video` ou `document`.
- Se não houver variáveis de body, o payload ainda envia componente `body` com `parameters: []`; isso pode ser desnecessário e deve ser validado em implementação.

### Disparo Manual

- O Disparo Manual seleciona conta Meta e template, carrega `TMP_Componentes`, solicita apenas variáveis textuais e grava lote/itens com `DMI_VariaveisJson`.
- O processamento imediato/worker chama `MetaService::enviarTemplate($numero, $template, $variaveis)`.
- Não há campo no lote ou item para mídia por destinatário, URL de mídia, media id, filename ou caption.

### Campanhas

- A criação de campanha salva template, lista e mapeamento das variáveis textuais em `campanha_variaveis`.
- O worker lê `campanha_variaveis`, monta `$parametros` a partir dos campos do contato e chama `MetaService::enviarTemplate()`.
- Não há mapeamento de campo de contato para mídia, nem mídia fixa por campanha.

### Worker

- O worker de campanhas busca `templates_meta`, lê variáveis textuais da campanha, monta parâmetros e chama `MetaService::enviarTemplate()`.
- O processamento de Disparo Manual em `DisparoManualQueueService` também chama `MetaService::enviarTemplate()` com as variáveis salvas.
- Portanto, o ponto central de mudança de payload é `MetaService::enviarTemplate()`, mas os dados necessários precisam chegar a ele a partir das telas/controllers/fila.

## O que falta para IMAGE, VIDEO e DOCUMENT

### Criação de templates com header de mídia

Para criar templates com `HEADER` de `IMAGE`, `VIDEO` ou `DOCUMENT`, falta:

1. Habilitar os tipos na view.
2. Definir UX para a mídia de exemplo exigida pela Meta na criação/aprovação:
   - URL pública informada pelo usuário; ou
   - upload temporário local e obtenção de handle/ID aceito pela Meta antes do `POST /message_templates`.
3. Ajustar `MetaService::criarTemplate()` para montar o componente de header com o formato correto e exemplo de mídia, em vez de enviar apenas `type` e `format`.
4. Salvar no JSON local (`TMP_Componentes`) os metadados úteis para preview e envio: `format`, tipo de mídia, se é estática ou variável, URL de exemplo e/ou handle retornado pela Meta.
5. Validar tamanho, MIME type e extensão por tipo:
   - `IMAGE`: jpeg/png/webp conforme aceito pela Meta.
   - `VIDEO`: mp4/3gpp conforme aceito pela Meta.
   - `DOCUMENT`: pdf ou demais tipos permitidos pela política/API vigente.

### Uso/envio de templates com header de mídia

Para enviar templates com mídia dinâmica falta:

1. Extrair do template se há header `IMAGE`, `VIDEO` ou `DOCUMENT`.
2. Coletar mídia no Disparo Manual e Campanhas:
   - mídia fixa para todos os destinatários; e/ou
   - mídia por destinatário via campo da planilha/lista/contato.
3. Decidir se o envio usará:
   - link público direto no parâmetro (`image.link`, `video.link`, `document.link`); ou
   - upload prévio para a Meta e envio por `id` (`image.id`, `video.id`, `document.id`).
4. Ajustar `MetaService::enviarTemplate()` para montar `components` dinamicamente, incluindo `header` quando o template exigir mídia, por exemplo:

```json
{
  "type": "header",
  "parameters": [
    {
      "type": "image",
      "image": { "link": "https://.../imagem.jpg" }
    }
  ]
}
```

Para documento, incluir `filename` quando aplicável:

```json
{
  "type": "header",
  "parameters": [
    {
      "type": "document",
      "document": {
        "link": "https://.../arquivo.pdf",
        "filename": "arquivo.pdf"
      }
    }
  ]
}
```

5. Não enviar componente `body` vazio quando o template não tiver variáveis de body, se a Meta rejeitar esse formato.
6. Registrar no histórico (`disparos` e `conversa_mensagens`) os metadados da mídia para auditoria/preview, ou ao menos preservá-los no `retorno`/payload.

## URL pública vs upload local/Meta

### Opção A: URL pública

Mais simples para envio se a Meta aceitar `link` no template message. Requer:

- Validar URL HTTPS pública, estável e acessível pela Meta.
- Evitar URLs autenticadas, temporárias curtas ou bloqueadas por hotlink/WAF.
- Para Campanhas, permitir campo de contato com URL de mídia.

### Opção B: upload local temporário + upload Meta

Mais robusta quando o usuário tem arquivo local ou quando a criação do template exige handle de exemplo. Requer:

- Campo `enctype="multipart/form-data"` nos formulários.
- Storage temporário com limpeza.
- Validação de tamanho/MIME.
- Método no `MetaService` para upload de mídia/arquivo/handle conforme endpoint Meta vigente.
- Persistência de `media_id`/handle, se necessário, e política de expiração/reupload.

### Recomendação

Implementar em duas fases:

1. **Fase 1:** URL pública para envio e exemplo de criação quando possível, com validação forte e preview por URL.
2. **Fase 2:** upload local temporário + upload/handle Meta para melhorar UX e cobrir casos em que a Meta exige handle específico na criação.

Antes de codar, confirmar na versão da Graph API usada em `MTA_UrlBase` o formato atual exigido para `example.header_handle` na criação de template de mídia.

## Necessidade de migration

Para criação/envio mínimo usando `TMP_Componentes` como fonte da verdade, a migration em `templates_meta` pode não ser obrigatória. Porém, para operação confiável e consultas simples, recomenda-se adicionar metadados normalizados.

### Migration recomendada para `templates_meta`

```sql
ALTER TABLE templates_meta
    ADD COLUMN TMP_HeaderTipo VARCHAR(20) NULL AFTER TMP_Status,
    ADD COLUMN TMP_HeaderMidiaModo ENUM('nenhuma','estatica','dinamica') NOT NULL DEFAULT 'nenhuma' AFTER TMP_HeaderTipo,
    ADD COLUMN TMP_HeaderMidiaUrlExemplo VARCHAR(1024) NULL AFTER TMP_HeaderMidiaModo,
    ADD COLUMN TMP_HeaderMidiaHandle VARCHAR(255) NULL AFTER TMP_HeaderMidiaUrlExemplo,
    ADD COLUMN TMP_HeaderDocumentoNome VARCHAR(255) NULL AFTER TMP_HeaderMidiaHandle;
```

### Possíveis migrations para disparos/campanhas

Se a mídia for fixa por lote/campanha:

```sql
ALTER TABLE disparo_manual_lotes
    ADD COLUMN DML_HeaderMidiaTipo VARCHAR(20) NULL,
    ADD COLUMN DML_HeaderMidiaUrl VARCHAR(1024) NULL,
    ADD COLUMN DML_HeaderMidiaId VARCHAR(255) NULL,
    ADD COLUMN DML_HeaderDocumentoNome VARCHAR(255) NULL;

ALTER TABLE campanhas
    ADD COLUMN CAM_HeaderMidiaTipo VARCHAR(20) NULL,
    ADD COLUMN CAM_HeaderMidiaOrigem ENUM('fixa','campo_contato') NULL,
    ADD COLUMN CAM_HeaderMidiaUrl VARCHAR(1024) NULL,
    ADD COLUMN CAM_HeaderMidiaCampo VARCHAR(120) NULL,
    ADD COLUMN CAM_HeaderMidiaId VARCHAR(255) NULL,
    ADD COLUMN CAM_HeaderDocumentoNome VARCHAR(255) NULL;
```

Se a mídia puder variar por destinatário no Disparo Manual, adicionar no item:

```sql
ALTER TABLE disparo_manual_itens
    ADD COLUMN DMI_HeaderMidiaUrl VARCHAR(1024) NULL,
    ADD COLUMN DMI_HeaderMidiaId VARCHAR(255) NULL,
    ADD COLUMN DMI_HeaderDocumentoNome VARCHAR(255) NULL;
```

Alternativa mais flexível: criar colunas JSON (`*_MidiaJson`) para evitar múltiplas colunas, mas isso reduz facilidade de filtros e auditoria.

## Arquivos que precisariam ser alterados

### Backend

- `app/Controllers/TemplateController.php`: validar entrada de mídia, processar upload/URL e passar dados normalizados ao service.
- `app/Services/MetaService.php`: criar payload de template com exemplos de mídia, adicionar métodos de upload/handle se necessário e montar payload de envio com header de mídia.
- `app/Models/TemplateMeta.php`: persistir/ler campos normalizados de header/mídia ou enriquecer `TMP_Componentes`.
- `app/Controllers/DisparoController.php`: coletar mídia fixa/por linha, validar, salvar no lote/item e repassar ao envio.
- `app/Models/DisparoManual.php`: persistir metadados de mídia em lote/item.
- `app/Services/DisparoManualQueueService.php`: ler metadados de mídia e chamar `enviarTemplate()` com dados do header.
- `app/Controllers/CampanhaController.php`: coletar configuração de mídia fixa ou mapeamento de campo do contato.
- `app/Models/Campanha.php` e/ou `app/Models/CampanhaVariavel.php`: persistir origem/mapeamento de mídia.
- `worker.php`: montar mídia da campanha antes de chamar `MetaService::enviarTemplate()`.
- `app/Models/Disparo.php` e `app/Models/Conversa.php`: opcionalmente registrar metadados de mídia enviados.

### Frontend/views

- `app/Views/templates/index.php`: habilitar `IMAGE`, `VIDEO`, `DOCUMENT`, adicionar campos de URL/upload e preview do header.
- `app/Views/disparos/index.php`: adicionar campos de mídia quando template exigir header de mídia; ajustar preview.
- `app/Views/campanhas/index.php`: adicionar configuração de mídia fixa ou mapeada por campo; ajustar preview/mapeamento.
- `app/Views/campanhas/preview.php`: exibir mídia configurada/mapeada na prévia.
- `public/assets/js/app.js`: detectar header de mídia em `TMP_Componentes`, renderizar preview e exigir campos adequados.

### Banco

- Nova migration em `database/migrations/` para campos de `templates_meta` e, conforme decisão de UX, campos em `disparo_manual_lotes`, `disparo_manual_itens` e `campanhas`.

## Plano de implementação sugerido

1. Confirmar formato Meta vigente para criação de template com `HEADER` de mídia na versão de Graph API usada em `MTA_UrlBase`.
2. Definir UX: URL pública inicialmente, upload local em fase posterior, ou ambos.
3. Criar migration para metadados normalizados de header/mídia.
4. Atualizar `TemplateMeta` para extrair `HEADER.format` de `TMP_Componentes` e preencher campos normalizados em sync/criação.
5. Habilitar campos de mídia no modal de criação e validações frontend/backend.
6. Ajustar `MetaService::criarTemplate()` para enviar exemplos/handles corretos para `IMAGE`, `VIDEO`, `DOCUMENT`.
7. Ajustar previews de templates, Disparo Manual e Campanhas para headers de mídia.
8. Atualizar Disparo Manual para coletar mídia fixa/por item e persistir na fila.
9. Atualizar Campanhas para coletar mídia fixa ou mapear campo de contato.
10. Refatorar `MetaService::enviarTemplate()` para montar componentes `header`, `body` e botões de forma condicional.
11. Atualizar worker e `DisparoManualQueueService` para passar metadados de mídia ao envio.
12. Adicionar logs seguros do payload, mascarando dados sensíveis e evitando guardar URLs privadas se isso for política do produto.
13. Testar com templates aprovados de imagem, vídeo e documento em ambiente Meta real/sandbox.

## Riscos

- A Meta pode exigir `header_handle` na criação, e simples URL pública pode não bastar para aprovação.
- Media IDs podem expirar ou ser vinculados a conta/phone/WABA, exigindo reupload.
- URLs públicas podem falhar por bloqueio, expiração, redirect, MIME incorreto ou tamanho acima do limite.
- Vídeos/documentos grandes podem estourar timeout/memória em upload local.
- Campanhas com mídia por contato podem ter alta taxa de erro se URLs da planilha estiverem inválidas.
- Enviar componente `body` vazio ou header incompatível com o template aprovado pode gerar erro de API por destinatário.
- Previews podem expor URLs de mídia sensíveis se não houver controle.
- A tabela `templates_meta` não tem migration no repo; é necessário validar schema real antes de aplicar ALTERs.

## Como testar

### Testes de criação

1. Criar template `IMAGE` com URL/arquivo de exemplo, confirmar retorno Meta com `id` e status `PENDING`/`APPROVED`.
2. Sincronizar templates e confirmar que o header `IMAGE` foi salvo em `TMP_Componentes` e campos normalizados.
3. Repetir para `VIDEO` e `DOCUMENT`.
4. Verificar preview na listagem de templates.

### Testes de Disparo Manual

1. Selecionar template `IMAGE` aprovado, informar URL pública de imagem e enviar para um número de teste.
2. Confirmar no retorno/payload que foi enviado componente `header` com `type: image`.
3. Confirmar `disparos`, `conversa_mensagens` e webhook/status.
4. Repetir com `VIDEO` e `DOCUMENT`, validando `filename` para documento.
5. Testar erro com URL inválida, URL sem HTTPS, MIME incompatível e mídia indisponível.

### Testes de Campanhas

1. Campanha com mídia fixa: todos os contatos recebem a mesma imagem/vídeo/documento.
2. Campanha com mídia por campo do contato: cada contato recebe sua URL própria.
3. Validar worker em modo teste e modo real.
4. Validar que contatos com mídia inválida viram erro individual sem cancelar toda a campanha.
5. Validar retomada do worker e webhook de confirmação.

## Conclusão

O sistema já cria templates reais via API Meta e já sincroniza/lista templates. Porém, suporte a cabeçalho de mídia está apenas sinalizado na interface e não está funcional. Falta capturar mídia de exemplo na criação, montar `example/header_handle` ou equivalente exigido pela Meta, persistir metadados, ajustar previews e alterar o payload de envio para incluir componente `header` com `image`, `video` ou `document` quando o template exigir mídia.
