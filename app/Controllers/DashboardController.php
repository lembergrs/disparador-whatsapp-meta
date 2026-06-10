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

        $clientes = 0;
        $contasMeta = 0;
        $templates = 0;
        $contatos = 0;
        $conversas = 0;
        $naoLidas = 0;
        $campanhas = 0;
        $mensagensRecebidas = 0;
        $metaConta = null;
        $ultimasCampanhas = [];
        $ultimasConversas = [];
        $cliente = null;
        $consumo = null;

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

            $campanhas = $db->query("
                SELECT COUNT(*) total
                FROM campanhas
            ")->fetch()['total'];

            $conversas = $db->query("
                SELECT COUNT(*) total
                FROM conversas
                WHERE CVS_Ativo = 'S'
            ")->fetch()['total'];

            $naoLidas = $db->query("
                SELECT COUNT(*) total
                FROM conversas
                WHERE CVS_Ativo = 'S'
                AND CVS_NaoLida = 'S'
            ")->fetch()['total'];

            $ultimasCampanhas = $db->query("
                SELECT *
                FROM campanhas
                ORDER BY CAM_ID DESC
                LIMIT 5
            ")->fetchAll();

            $ultimasConversas = $db->query("
                SELECT *
                FROM conversas
                WHERE CVS_Ativo = 'S'
                ORDER BY CVS_DataUltimaMensagem DESC
                LIMIT 10
            ")->fetchAll();

        }else{

            $cliId = $usuario['CLI_ID'];
            
            $clienteModel =
                new \Models\Cliente();

            $cliente =
                $clienteModel->buscarComPlano(
                    $cliId
                );

            $consumoModel =
                new \Models\ConsumoMensal();

            $consumo =
                $consumoModel->buscarMesAtual(
                    $cliId
                );

            $sql = $db->prepare("
                SELECT COUNT(*) total
                FROM contatos
                WHERE CLI_ID = ?
                AND CON_Ativo = 'S'
            ");
            $sql->execute([$cliId]);
            $contatos = $sql->fetch()['total'];

            $sql = $db->prepare("
                SELECT COUNT(*) total
                FROM campanhas
                WHERE CLI_ID = ?
            ");
            $sql->execute([$cliId]);
            $campanhas = $sql->fetch()['total'];

            $sql = $db->prepare("
                SELECT COUNT(*) total
                FROM conversas
                WHERE CLI_ID = ?
                AND CVS_Ativo = 'S'
            ");
            $sql->execute([$cliId]);
            $conversas = $sql->fetch()['total'];

            $sql = $db->prepare("
                SELECT COUNT(*) total
                FROM conversas
                WHERE CLI_ID = ?
                AND CVS_Ativo = 'S'
                AND CVS_NaoLida = 'S'
            ");
            $sql->execute([$cliId]);
            $naoLidas = $sql->fetch()['total'];

            $sql = $db->prepare("
                SELECT COUNT(*) total
                FROM conversa_mensagens m
                INNER JOIN conversas c
                    ON c.CVS_ID = m.CVS_ID
                WHERE c.CLI_ID = ?
                AND m.MSG_Direcao = 'recebida'
            ");
            $sql->execute([$cliId]);
            $mensagensRecebidas = $sql->fetch()['total'];

            $sql = $db->prepare("
                SELECT *
                FROM meta_contas
                WHERE CLI_ID = ?
                AND MTA_Ativo = 'S'
                ORDER BY MTA_ID DESC
                LIMIT 1
            ");
            $sql->execute([$cliId]);
            $metaConta = $sql->fetch();

            $sql = $db->prepare("
                SELECT *
                FROM campanhas
                WHERE CLI_ID = ?
                ORDER BY CAM_ID DESC
                LIMIT 5
            ");
            $sql->execute([$cliId]);
            $ultimasCampanhas = $sql->fetchAll();

            $sql = $db->prepare("
                SELECT *
                FROM conversas
                WHERE CLI_ID = ?
                AND CVS_Ativo = 'S'
                ORDER BY CVS_DataUltimaMensagem DESC
                LIMIT 10
            ");
            $sql->execute([$cliId]);
            $ultimasConversas = $sql->fetchAll();
        }

        $this->view(
            'dashboard/index',
            [
                'titulo' => 'Dashboard',
                'usuario' => $usuario,
                'clientes' => $clientes,
                'cliente' => $cliente,
                'contasMeta' => $contasMeta,
                'templates' => $templates,
                'contatos' => $contatos,
                'conversas' => $conversas,
                'naoLidas' => $naoLidas,
                'campanhas' => $campanhas,
                'mensagensRecebidas' => $mensagensRecebidas,
                'metaConta' => $metaConta,
                'ultimasCampanhas' => $ultimasCampanhas,
                'ultimasConversas' => $ultimasConversas,
                'consumo' => $consumo
            ]
        );
    }
}