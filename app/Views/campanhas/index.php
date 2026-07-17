<?php
$limitePlanoDisparador = isset($clientePlano['PLA_LimiteMensagens']) ? (int) $clientePlano['PLA_LimiteMensagens'] : null;
$mensagensUsadasDisparador = isset($consumoMes['CMS_Mensagens']) ? (int) $consumoMes['CMS_Mensagens'] : 0;
$mensagensDisponiveisDisparador = $limitePlanoDisparador !== null ? max(0, $limitePlanoDisparador - $mensagensUsadasDisparador) : null;
$limiteMetaLabel = \Services\MetaService::formatarLimiteConversasMeta($metaContaLimite['MTA_MessagingLimit'] ?? null);
$avisoLimiteMeta = \Services\MetaService::avisoDesatualizacaoMeta($metaContaLimite['MTA_UltimaVerificacao'] ?? null);
?>
<div class="mb-3">

    <button
    type="button"
    class="btn btn-outline-info btn-sm"
    id="btnAjudaVariaveis"
    >
        <i class="fas fa-question-circle"></i>
        Como usar variáveis
    </button>

</div>

<div
class="alert alert-info"
id="cardAjudaVariaveis"
style="display:none;"
>

    <h5>
        <i class="fas fa-info-circle"></i>
        Como usar as variáveis
    </h5>

    <p>
        As variáveis do template são os campos que aparecem como
        <strong>{{1}}</strong>, <strong>{{2}}</strong>, <strong>{{3}}</strong>.
    </p>

    <p>
        Ao criar a campanha, escolha qual coluna da sua planilha será usada em cada variável.
    </p>

    <p>
        Exemplo:
    </p>

    <ul>
        <li><strong>{{1}}</strong> → Nome</li>
        <li><strong>{{2}}</strong> → Valor</li>
        <li><strong>{{3}}</strong> → Vencimento</li>
    </ul>

    <p class="mb-0">
        Se sua planilha tiver as colunas Nome, Telefone, Valor e Vencimento,
        o sistema usará esses dados automaticamente para cada contato.
    </p>

</div>

<div class="row mb-3">
    <div class="col-md-6">
        <div class="callout callout-info h-100 mb-0">
            <h5>Plano Disparador</h5>
            <?php if($mensagensDisponiveisDisparador !== null){ ?>
                <p class="mb-1">Você possui <?= number_format($mensagensDisponiveisDisparador, 0, ',', '.'); ?> mensagens disponíveis neste ciclo.</p>
            <?php }else{ ?>
                <p class="mb-1">Consulte seu plano no Disparador para acompanhar a franquia comercial de mensagens.</p>
            <?php } ?>
            <small class="text-muted">Este limite faz parte do plano contratado no Disparador e considera mensagens processadas pela plataforma.</small>
        </div>
    </div>
    <div class="col-md-6">
        <div class="callout callout-warning h-100 mb-0">
            <h5>Limite de conversas da Meta</h5>
            <p class="mb-1">Limite atualmente informado pela Meta: <?= htmlspecialchars($limiteMetaLabel, ENT_QUOTES, 'UTF-8'); ?></p>
            <small>Este limite é controlado exclusivamente pela Meta e é diferente da quantidade de mensagens do seu plano.</small><br><small>A aprovação e o processamento dos envios também dependem das regras e limites aplicados pela Meta.</small>
            <?php if(!empty($avisoLimiteMeta)){ ?><br><small class="text-muted"><?= htmlspecialchars($avisoLimiteMeta, ENT_QUOTES, 'UTF-8'); ?></small><?php } ?>
        </div>
    </div>
</div>

<div class="card">

    <div class="card-header">

        <button
        class="btn btn-success"
        id="btnNovaCampanha"
        data-toggle="modal"
        data-target="#modalCampanha"
        >

            Nova Campanha

        </button>

    </div>

    <div class="card-body">

        <table
        class="table table-bordered table-striped table-hover datatable"
        id="tabelaCampanhas"
        >

            <thead>

                <tr>

                    <th>ID</th>
                    <th>Nome</th>
                    <th>Status</th>
                    <th>Contatos</th>
                    <th>Enviados</th>
                    <th>Erros</th>
                    <th>Ações</th>

                </tr>

            </thead>

            <tbody>

            <?php foreach($campanhas as $campanha){ ?>

            <tr>

                <td>
                    <?= $campanha['CAM_ID']; ?>
                </td>

                <td>
                    <?= $campanha['CAM_Nome']; ?>
                </td>

                <td>

                <?php
                $status = $campanha['CAM_Status'];

                $badges = [
                    'rascunho' => 'secondary',
                    'agendada' => 'warning',
                    'processando' => 'info',
                    'finalizada' => 'success',
                    'cancelada' => 'danger'
                ];

                $labels = [
                    'rascunho' => 'Rascunho',
                    'agendada' => 'Agendada',
                    'processando' => 'Processando',
                    'finalizada' => 'Finalizada',
                    'cancelada' => 'Cancelada'
                ];

                $classe = $badges[$status] ?? 'secondary';
                $label = $labels[$status] ?? ucfirst($status);

                ?>

                <span class="badge badge-<?= $classe; ?>">
                    <?= $label; ?>
                </span>

                </td>

                <td>
                    <?= $campanha['CAM_TotalContatos']; ?>
                </td>

                <td>
                    <?= $campanha['CAM_TotalEnviados']; ?>
                </td>

                <td>
                    <?= $campanha['CAM_TotalErros']; ?>
                </td>

                <td>

                    <a
                    href="<?= BASE_URL; ?>/index.php?url=campanha/detalhes&id=<?= $campanha['CAM_ID']; ?>"
                    class="btn btn-info btn-sm"
                    >
                        Detalhes
                    </a>

                    <a
                    href="<?= BASE_URL; ?>/index.php?url=campanha/preview&id=<?= $campanha['CAM_ID']; ?>"
                    class="btn btn-warning btn-sm"
                    >

                    Preview

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
id="modalCampanha"
>

<div class="modal-dialog modal-lg">

<div class="modal-content">

<form
method="POST"
action="<?= BASE_URL; ?>/index.php?url=campanha/criar"
>
<?= \Core\Csrf::input(); ?>


<div class="modal-header">

<h4>Nova Campanha</h4>

</div>

<div class="modal-body">

<div class="form-group">

<label>Nome</label>

<input
type="text"
name="nome"
class="form-control"
required
>

</div>

<div class="form-group">

<label>Descrição</label>

<textarea
name="descricao"
class="form-control"
rows="3"
></textarea>

</div>

<div class="form-group">

<label>Template</label>

<select
name="template"
id="templateCampanha"
class="form-control"
required
>

<option value="">
<?= empty($templates) ? 'Nenhum template aprovado disponível para envio nesta conta.' : 'Selecione'; ?>
</option>

<?php foreach($templates as $template){ ?>

<option
value="<?= $template['TMP_ID']; ?>"
data-componentes="<?= htmlspecialchars(base64_encode($template['TMP_Componentes']), ENT_QUOTES); ?>"
data-header-tipo="<?= htmlspecialchars($template['TMP_HeaderTipo'] ?? '', ENT_QUOTES); ?>"
data-header-midia-url-exemplo="<?= htmlspecialchars($template['TMP_HeaderMidiaUrlExemplo'] ?? '', ENT_QUOTES); ?>"
data-header-documento-nome="<?= htmlspecialchars($template['TMP_HeaderDocumentoNome'] ?? '', ENT_QUOTES); ?>"
>
    <?= $template['TMP_Nome']; ?>
</option>

<?php } ?>

</select>


<div class="form-group">

<label>Lista de Contatos</label>

<select
name="lista"
class="form-control"
required
>

<option value="">
Selecione uma lista
</option>

<?php foreach($listas as $lista){ ?>

<option value="<?= $lista['LST_ID']; ?>">

<?= $lista['LST_Nome']; ?>

(<?= $lista['total_contatos']; ?> contatos)

</option>

<?php } ?>

</select>

</div>

<div
id="areaMapeamentoVariaveis"
class="mt-3"
style="display:none;"
>

    <div class="alert alert-warning">
        <strong>Mapeamento das variáveis</strong><br>
        Escolha qual campo da planilha será usado em cada variável do template.
    </div>

    <div id="camposVariaveis"></div>

</div>

<div
id="previewTemplateCampanha"
class="mt-3"
style="display:none;"
>

    <div class="card card-outline card-success">

        <div class="card-header">

            <h3 class="card-title">
                Prévia da mensagem
            </h3>

        </div>

        <div class="card-body">

            <div
            id="conteudoPreviewTemplate"
            style="white-space: pre-line;"
            ></div>

        </div>

    </div>

</div>

<div class="form-group">

    <label>Data/Hora do envio</label>

    <input
        type="datetime-local"
        name="data_agendamento"
        class="form-control"
        required
    >

</div>

</div>

<div class="modal-footer">

<button
type="submit"
class="btn btn-success"
>

Criar Campanha

</button>

</div>

</form>

</div>

</div>

</div>

<script>

window.CAMPOS_CONTATO =
<?= json_encode($camposContato, JSON_UNESCAPED_UNICODE); ?>;

document.addEventListener('click', function(e){

    var botao =
        e.target.closest('#btnAjudaVariaveis');

    if(!botao){
        return;
    }

    e.preventDefault();

    var card =
        document.getElementById('cardAjudaVariaveis');

    if(!card){
        return;
    }

    if(card.style.display === 'none' || card.style.display === ''){
        card.style.display = 'block';
    }else{
        card.style.display = 'none';
    }

});

</script>