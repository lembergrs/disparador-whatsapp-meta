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
                    <?= $campanha['CAM_Status']; ?>
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

                    <button
                    class="btn btn-info btn-sm"
                    >
                        Detalhes
                    </button>

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
class="form-control"
required
>

<option value="">
Selecione
</option>

<?php foreach($templates as $template){ ?>

<option
value="<?= $template['TMP_ID']; ?>"
>

<?= $template['TMP_Nome']; ?>

</option>

<?php } ?>

</select>

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