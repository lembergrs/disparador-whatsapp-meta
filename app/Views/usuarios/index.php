<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title">Usuários da conta</h3>
        <span class="badge badge-info">
            <?= (int) $usuariosAtivos; ?> de <?= (int) $limiteUsuarios; ?> usuários ativos
        </span>
    </div>

    <div class="card-body">
        <?php if($limiteAtingido){ ?>
            <div class="alert alert-warning">
                Você atingiu o limite de usuários do seu plano. Faça upgrade para adicionar mais usuários.
            </div>
        <?php } ?>

        <form method="post" action="<?= BASE_URL; ?>/index.php?url=usuario/salvar" class="mb-4">
            <?php if($adminInterno){ ?>
                <input type="hidden" name="cliente_id" value="<?= (int) $clienteSelecionadoId; ?>">
            <?php } ?>
            <input type="hidden" name="id" id="usuario_id">
            <div class="row">
                <div class="col-md-4">
                    <label>Nome</label>
                    <input type="text" name="nome" id="usuario_nome" class="form-control" required>
                </div>
                <div class="col-md-4">
                    <label>E-mail</label>
                    <input type="email" name="email" id="usuario_email" class="form-control" required>
                </div>
                <div class="col-md-3">
                    <label>Senha <small>(obrigatória ao criar)</small></label>
                    <input type="password" name="senha" class="form-control" minlength="6">
                </div>
                <div class="col-md-1 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary" <?= $limiteAtingido ? 'data-limite="S"' : ''; ?>>Salvar</button>
                </div>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>E-mail</th>
                        <th>Nível</th>
                        <th>Status</th>
                        <th width="260">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($usuariosCliente as $u){ ?>
                        <tr>
                            <td><?= htmlspecialchars($u['USU_Nome']); ?></td>
                            <td><?= htmlspecialchars($u['USU_Email']); ?></td>
                            <td><?= htmlspecialchars($u['USU_Nivel']); ?></td>
                            <td><?= $u['USU_Ativo'] == 'S' ? 'Ativo' : 'Inativo'; ?></td>
                            <td>
                                <button type="button" class="btn btn-sm btn-secondary btn-editar"
                                    data-id="<?= (int) $u['USU_ID']; ?>"
                                    data-nome="<?= htmlspecialchars($u['USU_Nome']); ?>"
                                    data-email="<?= htmlspecialchars($u['USU_Email']); ?>">
                                    Editar
                                </button>
                                <?php if($u['USU_Ativo'] == 'S'){ ?>
                                    <a class="btn btn-sm btn-warning" href="<?= BASE_URL; ?>/index.php?url=usuario/inativar&id=<?= (int) $u['USU_ID']; ?><?= $adminInterno ? '&cliente=' . (int) $clienteSelecionadoId : ''; ?>" onclick="return confirm('Inativar usuário?')">Inativar</a>
                                <?php }else{ ?>
                                    <a class="btn btn-sm btn-success" href="<?= BASE_URL; ?>/index.php?url=usuario/ativar&id=<?= (int) $u['USU_ID']; ?><?= $adminInterno ? '&cliente=' . (int) $clienteSelecionadoId : ''; ?>">Ativar</a>
                                <?php } ?>
                                <form method="post" action="<?= BASE_URL; ?>/index.php?url=usuario/senha" class="d-inline">
                                    <?php if($adminInterno){ ?>
                                        <input type="hidden" name="cliente_id" value="<?= (int) $clienteSelecionadoId; ?>">
                                    <?php } ?>
                                    <input type="hidden" name="id" value="<?= (int) $u['USU_ID']; ?>">
                                    <input type="password" name="senha" class="form-control form-control-sm d-inline-block" style="width: 105px" placeholder="Nova senha" minlength="6" required>
                                    <button type="submit" class="btn btn-sm btn-info">Alterar senha</button>
                                </form>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.btn-editar').forEach(function(botao){
    botao.addEventListener('click', function(){
        document.getElementById('usuario_id').value = botao.dataset.id;
        document.getElementById('usuario_nome').value = botao.dataset.nome;
        document.getElementById('usuario_email').value = botao.dataset.email;
    });
});
</script>
