<?php

use Core\Auth;

$usuario = Auth::usuario();

?>

<div class="row">

    <?php if($usuario['nivel'] == 'admin'){ ?>

    <div class="col-lg-3 col-6">

        <div class="small-box bg-info">

            <div class="inner">

                <h3><?= $clientes; ?></h3>

                <p>Clientes</p>

            </div>

            <div class="icon">

                <i class="fas fa-users"></i>

            </div>

        </div>

    </div>

    <?php } ?>

    <div class="col-lg-3 col-6">

        <div class="small-box bg-success">

            <div class="inner">

                <h3><?= $contasMeta; ?></h3>

                <p>Contas Meta</p>

            </div>

            <div class="icon">

                <i class="fab fa-whatsapp"></i>

            </div>

        </div>

    </div>

    <div class="col-lg-3 col-6">

        <div class="small-box bg-warning">

            <div class="inner">

                <h3><?= $templates; ?></h3>

                <p>Templates</p>

            </div>

            <div class="icon">

                <i class="fas fa-file-alt"></i>

            </div>

        </div>

    </div>

    <div class="col-lg-3 col-6">

        <div class="small-box bg-danger">

            <div class="inner">

                <h3><?= number_format($contatos,0,',','.'); ?></h3>

                <p>Contatos</p>

            </div>

            <div class="icon">

                <i class="fas fa-address-book"></i>

            </div>

        </div>

    </div>

</div>

<div class="card">

    <div class="card-body">

        <h4>

            Bem-vindo,
            <?= $usuario['nome']; ?>

        </h4>

        <p>

            Utilize o menu lateral para acessar os módulos do sistema.

        </p>

    </div>

</div>