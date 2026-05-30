<?php

namespace Controllers;

use Core\Controller;
use Core\Auth;
use Core\Database;

class DashboardController extends Controller
{
    public function index()
    {
        Auth::check();

        $usuario = Auth::usuario();

        $db = Database::getInstance();

        if($usuario['nivel'] == 'admin'){

            $clientes = $db->query("
                SELECT COUNT(*) total
                FROM clientes
                WHERE CLI_Ativo = 'S'
            ")->fetch()['total'];

            $contasMeta = $db->query("
                SELECT COUNT(*) total
                FROM meta_contas
                WHERE MTA_Ativo = 'S'
            ")->fetch()['total'];

            $templates = $db->query("
                SELECT COUNT(*) total
                FROM templates_meta
                WHERE TMP_Ativo = 'S'
            ")->fetch()['total'];

            $contatos = $db->query("
                SELECT COUNT(*) total
                FROM contatos
                WHERE CON_Ativo = 'S'
            ")->fetch()['total'];

        }else{

            $cliId = $usuario['CLI_ID'];

            $clientes = 1;

            $sql = $db->prepare("
                SELECT COUNT(*) total
                FROM meta_contas
                WHERE CLI_ID = ?
                AND MTA_Ativo = 'S'
            ");

            $sql->execute([$cliId]);

            $contasMeta = $sql->fetch()['total'];

            $sql = $db->prepare("
                SELECT COUNT(*) total
                FROM templates_meta tm
                INNER JOIN meta_contas mc
                    ON tm.MTA_ID = mc.MTA_ID
                WHERE mc.CLI_ID = ?
                AND tm.TMP_Ativo = 'S'
            ");

            $sql->execute([$cliId]);

            $templates = $sql->fetch()['total'];

            $sql = $db->prepare("
                SELECT COUNT(*) total
                FROM contatos
                WHERE CLI_ID = ?
                AND CON_Ativo = 'S'
            ");

            $sql->execute([$cliId]);

            $contatos = $sql->fetch()['total'];
        }

        $this->view(
            'dashboard/index',
            [
                'titulo' => 'Dashboard',
                'clientes' => $clientes,
                'contasMeta' => $contasMeta,
                'templates' => $templates,
                'contatos' => $contatos
            ]
        );
    }
}