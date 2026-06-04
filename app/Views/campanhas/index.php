<div class="alert alert-info">

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
        class="table table-bordered"
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
    
    <div class="form-group">

    <label>Lista de Contatos</label>

    <select
    name="lista"
    class="form-control"
    required
    >

    <option value="">
    Selecione
    </option>

    <?php foreach($listas as $lista){ ?>

    <option value="<?= $lista['LST_ID']; ?>">
    <?= $lista['LST_Nome']; ?> (<?= $lista['total_contatos']; ?> contatos)
    </option>

    <?php } ?>

    </select>

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
Selecione
</option>

<?php foreach($templates as $template){ ?>

<option
value="<?= $template['TMP_ID']; ?>"
data-componentes="<?= htmlspecialchars(base64_encode($template['TMP_Componentes']), ENT_QUOTES); ?>"
>
    <?= $template['TMP_Nome']; ?>
</option>

<?= $template['TMP_Nome']; ?>

</option>

<?php } ?>

</select>

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
</script>