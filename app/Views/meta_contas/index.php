<?php
function metaContaQualityBadge($quality)
{
    $quality = strtoupper(trim((string) $quality));
    if($quality === ''){
        $quality = 'UNKNOWN';
    }

    $classes = [
        'GREEN' => 'success',
        'YELLOW' => 'warning',
        'RED' => 'danger',
        'UNKNOWN' => 'secondary'
    ];

    $classe = $classes[$quality] ?? 'secondary';

    return '<span class="badge badge-' . $classe . '">' . htmlspecialchars($quality, ENT_QUOTES, 'UTF-8') . '</span>';
}

function metaContaUltimaSincronizacao($valor)
{
    if(empty($valor)){
        return 'Nunca';
    }

    $timestamp = strtotime((string) $valor);
    if(!$timestamp){
        return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
    }

    return date('d/m/Y H:i', $timestamp);
}
?>
<div class="card">

<div class="card-header">

<button
id="btnNovaMeta"
class="btn btn-success"
data-toggle="modal"
data-target="#modalMeta"
>

Nova Conta Meta

</button>

</div>

<div class="card-body">

<?php if(empty($colunaWebhookVerifyTokenExiste)){ ?>

<div class="alert alert-warning">
A coluna <strong>MTA_WebhookVerifyToken</strong> não foi encontrada na tabela <strong>meta_contas</strong>.
Crie a coluna para salvar o Verify Token do webhook.
</div>

<?php } ?>

<?php if(empty($colunasAutoRespostaExistem)){ ?>

<div class="alert alert-warning">
As colunas de <strong>auto resposta</strong> não foram encontradas na tabela <strong>meta_contas</strong>.
Crie as colunas para salvar a configuração de auto resposta.
</div>

<?php } ?>

<table class="table table-bordered table-striped table-hover datatable">

<thead>

<tr>

<th>ID</th>
<th>Cliente</th>
<th>Conta</th>
<th>Número</th>
<th>Status</th>
<th>Status Meta</th>
<th>Quality</th>
<th>Última sincronização</th>
<th>Limite Meta</th>
<th>Ações</th>

</tr>

</thead>

<tbody>

<?php foreach($contas as $conta){ ?>

<tr data-meta-id="<?= (int) $conta['MTA_ID']; ?>">

<td><?= (int) $conta['MTA_ID']; ?></td>

<td><?= htmlspecialchars($conta['CLI_Nome'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>

<td class="js-meta-conta-nome"><?= htmlspecialchars($conta['MTA_Nome'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>

<td class="js-meta-numero"><?= htmlspecialchars($conta['MTA_NumeroTelefone'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>

<td class="js-meta-status-interno"><?= htmlspecialchars($conta['MTA_Status'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>

<td class="js-meta-operational-status"><?= htmlspecialchars(($conta['MTA_OperationalStatus'] ?? '') !== '' ? $conta['MTA_OperationalStatus'] : '—', ENT_QUOTES, 'UTF-8'); ?></td>

<td class="js-meta-quality-rating"><?= metaContaQualityBadge($conta['MTA_QualityRating'] ?? 'UNKNOWN'); ?></td>

<td class="js-meta-ultima-verificacao"><?= metaContaUltimaSincronizacao($conta['MTA_UltimaVerificacao'] ?? null); ?></td>

<td class="js-meta-messaging-limit" data-toggle="tooltip" title="Limite de conversas iniciadas pela empresa informado e controlado pela Meta."><?= htmlspecialchars(\Services\MetaService::formatarLimiteConversasMeta($conta['MTA_MessagingLimit'] ?? null), ENT_QUOTES, 'UTF-8'); ?></td>

<td class="text-nowrap">

<button
type="button"
class="btn btn-info btn-sm btnEditarMeta"
data-toggle="tooltip"
title="Editar conta Meta"

data-id="<?= (int) $conta['MTA_ID']; ?>"

data-cliente="<?= (int) $conta['CLI_ID']; ?>"

data-nome="<?= htmlspecialchars($conta['MTA_Nome'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"

data-phone="<?= htmlspecialchars($conta['MTA_PhoneNumberId'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"

data-waba="<?= htmlspecialchars($conta['MTA_WabaId'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"

data-webhook-token="<?= htmlspecialchars($conta['MTA_WebhookVerifyToken'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"

data-url="<?= htmlspecialchars($conta['MTA_UrlBase'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"

data-numero="<?= htmlspecialchars($conta['MTA_NumeroTelefone'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"

data-auto-resposta-ativa="<?= htmlspecialchars($conta['MTA_AutoRespostaAtiva'] ?? 'N', ENT_QUOTES, 'UTF-8'); ?>"

data-auto-resposta-texto="<?= htmlspecialchars($conta['MTA_AutoRespostaTexto'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"

data-auto-resposta-intervalo="<?= (int) ($conta['MTA_AutoRespostaIntervaloMinutos'] ?? 1440); ?>"
>

<i class="fas fa-edit"></i>

</button>

<a
href="#" data-post-url="<?= BASE_URL; ?>/index.php?url=metaConta/testar&id=<?= (int) $conta['MTA_ID']; ?>"
class="btn btn-success btn-sm"
data-toggle="tooltip"
title="Conectar ou reconectar o número do WhatsApp"
>

<i class="fas fa-plug"></i>

</a>

<button
 type="button"
 class="btn btn-info btn-sm btnSincronizarStatusMeta"
 data-id="<?= (int) $conta['MTA_ID']; ?>"
 data-toggle="tooltip"
 title="Atualizar informações do número diretamente na Meta"
>
<i class="fas fa-sync-alt"></i>
</button>

<a
href="#"
data-post-url="<?= BASE_URL; ?>/index.php?url=metaConta/inativar&id=<?= (int) $conta['MTA_ID']; ?>"
class="btn btn-danger btn-sm"
data-toggle="tooltip"
title="Excluir conta Meta"
data-confirm="Deseja inativar esta conta?"
>

<i class="fas fa-trash"></i>

</a>

</td>

</tr>

<?php } ?>

</tbody>

</table>

</div>

</div>


<div
class="modal fade"
id="modalMeta"
>

<div class="modal-dialog modal-lg">

<div class="modal-content">

<form
method="POST"
id="formMeta"
action="<?= BASE_URL; ?>/index.php?url=metaConta/salvar"
>

<div class="modal-header">

<h4 class="modal-title">
Nova Conta Meta
</h4>

</div>

<div class="modal-body">

<div class="form-group">

<label>Cliente</label>
<input
type="hidden"
name="id"
id="meta_id"
>
<select
name="cliente"
class="form-control"
required
>

<?php foreach($clientes as $cliente){ ?>

<option value="<?= $cliente['CLI_ID']; ?>">

<?= $cliente['CLI_Nome']; ?>

</option>

<?php } ?>

</select>

</div>

<div class="form-group">

<label>Nome da Conta</label>

<input
type="text"
name="nome"
class="form-control"
required
>

</div>

<div class="form-group">

<label>Phone Number ID</label>

<input
type="text"
name="phone_number_id"
class="form-control"
required
>

</div>

<div class="form-group">

<label>WABA ID</label>

<input
type="text"
name="waba_id"
class="form-control"
required
>

</div>

<div class="form-group">

<label>Token</label>

<textarea
name="token"
class="form-control"
rows="4"
></textarea>

</div>

<div class="form-group">

<label>Webhook Verify Token</label>

<div class="input-group">

<input
type="text"
name="webhook_verify_token"
id="webhook_verify_token"
class="form-control"
minlength="32"
maxlength="128"
required
>

<div class="input-group-append">

<button
type="button"
class="btn btn-outline-secondary"
id="btnGerarWebhookToken"
>
Gerar Token
</button>

</div>

</div>

<small class="form-text text-muted">
Use este token no campo Verify Token da configuração do Webhook da Meta. Se ficar vazio em uma conta nova, o sistema gera um token seguro automaticamente ao salvar.
</small>

</div>

<div class="alert alert-info meta-webhook-config-box">

<strong>Configuração do Webhook na Meta</strong><br>
Webhook URL:
<code id="metaWebhookUrl">https://disparador.net/public/webhook/meta.php</code>
<br>
Verify Token:
<code id="metaWebhookVerifyTokenPreview">Informe ou gere um token</code>

</div>

<div class="form-group">

<label>URL Base</label>

<input
type="text"
name="url_base"
class="form-control"
value="https://graph.facebook.com/<?= htmlspecialchars(META_GRAPH_VERSION, ENT_QUOTES, 'UTF-8'); ?>/"
required
>

</div>

<div class="form-group">

<label>Número WhatsApp</label>

<input
type="text"
name="numero"
class="form-control"
>

</div>

<div class="auto-resposta-section">

<hr>

<h5>Auto resposta</h5>

<div class="alert alert-info">
Essa mensagem será enviada automaticamente quando esse número receber uma mensagem. Ela não usa template e só funciona dentro da janela de atendimento de 24 horas da Meta.
</div>

<div class="form-group">

<label>Ativar auto resposta</label>

<div class="d-flex align-items-center">

<div class="form-check form-check-inline">
<input class="form-check-input" type="radio" name="auto_resposta_ativa" id="auto_resposta_ativa_n" value="N" checked>
<label class="form-check-label" for="auto_resposta_ativa_n">Não</label>
</div>

<div class="form-check form-check-inline">
<input class="form-check-input" type="radio" name="auto_resposta_ativa" id="auto_resposta_ativa_s" value="S">
<label class="form-check-label" for="auto_resposta_ativa_s">Sim</label>
</div>

</div>

</div>

<div class="form-group">

<label>Texto da auto resposta</label>

<textarea
name="auto_resposta_texto"
class="form-control"
rows="4"
></textarea>

</div>

<div class="form-group">

<label>Intervalo para repetir auto resposta</label>

<input
type="hidden"
name="auto_resposta_intervalo_minutos"
value="1440"
>

<div class="row">

<div class="col-md-6">
<label class="small text-muted">Horas</label>
<select name="auto_resposta_intervalo_horas" class="form-control auto-resposta-horas">
<?php for($hora = 0; $hora <= 24; $hora++){ ?>
<option value="<?= $hora; ?>"><?= str_pad($hora, 2, '0', STR_PAD_LEFT); ?></option>
<?php } ?>
</select>
</div>

<div class="col-md-6">
<label class="small text-muted">Minutos</label>
<select name="auto_resposta_intervalo_minutos_select" class="form-control auto-resposta-minutos">
<?php for($minuto = 0; $minuto <= 60; $minuto++){ ?>
<option value="<?= $minuto; ?>"><?= str_pad($minuto, 2, '0', STR_PAD_LEFT); ?></option>
<?php } ?>
</select>
</div>

</div>

<small class="form-text text-muted">
O total será salvo em minutos. Selecione pelo menos 1 minuto.
</small>

</div>

</div>

</div>

<div class="modal-footer">

<button
class="btn btn-success"
type="submit"
>

Salvar

</button>

</div>

</form>

</div>

</div>

</div>

<script>
(function(){
    function escapeHtml(valor)
    {
        return $('<div>').text(valor === null || valor === undefined || valor === '' ? '—' : valor).html();
    }

    function qualityBadge(quality)
    {
        quality = (quality || 'UNKNOWN').toString().toUpperCase();
        const classes = {
            GREEN: 'success',
            YELLOW: 'warning',
            RED: 'danger',
            UNKNOWN: 'secondary'
        };
        return '<span class="badge badge-' + (classes[quality] || 'secondary') + '">' + escapeHtml(quality) + '</span>';
    }

    function formatarDataSincronizacao(valor)
    {
        if(!valor){ return 'Nunca'; }
        const partes = valor.toString().match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/);
        if(!partes){ return escapeHtml(valor); }
        return partes[3] + '/' + partes[2] + '/' + partes[1] + ' ' + partes[4] + ':' + partes[5];
    }

    function mensagem(tipo, texto)
    {
        if(window.toastr && typeof window.toastr[tipo] === 'function'){
            window.toastr[tipo](texto);
            return;
        }
        alert(texto);
    }

    $(function(){
        $('[data-toggle="tooltip"]').tooltip();

        $(document).on('click', '.btnSincronizarStatusMeta', function(e){
            e.preventDefault();
            const botao = $(this);
            if(botao.prop('disabled')){ return; }

            const linha = botao.closest('tr');
            const htmlOriginal = botao.html();
            botao.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

            const form = new FormData();
            form.append('csrf_token', CSRF_TOKEN || '');
            form.append('conta_id', botao.data('id') || '');

            fetch(BASE_URL + '/index.php?url=configuracao/atualizarStatusMetaAjax', {
                method: 'POST',
                credentials: 'same-origin',
                body: form
            }).then(function(response){
                return response.json().then(function(json){
                    if(!response.ok || !json.ok){ throw json; }
                    return json;
                });
            }).then(function(json){
                const dados = json.dados || {};
                linha.find('.js-meta-operational-status').html(escapeHtml(dados.operational_status || '—'));
                linha.find('.js-meta-quality-rating').html(qualityBadge(dados.quality_rating || 'UNKNOWN'));
                linha.find('.js-meta-ultima-verificacao').html(formatarDataSincronizacao(dados.ultima_verificacao));
                linha.find('.js-meta-messaging-limit').html(escapeHtml(dados.messaging_limit_label || 'Limite da Meta ainda não disponível.'));
                if(dados.numero){ linha.find('.js-meta-numero').text(dados.numero); }
                if(dados.display_name){ linha.find('.js-meta-conta-nome').text(dados.display_name); }

                if($.fn.DataTable && $.fn.DataTable.isDataTable(linha.closest('table'))){
                    linha.closest('table').DataTable().row(linha).invalidate('dom').draw(false);
                }

                mensagem('success', json.mensagem || 'Dados sincronizados com sucesso.');
            }).catch(function(error){
                mensagem('error', (error && (error.mensagem || error.message)) || 'Não foi possível consultar a Meta.');
            }).finally(function(){
                botao.prop('disabled', false).html(htmlOriginal);
                botao.tooltip('dispose').tooltip();
            });
        });
    });
})();
</script>
