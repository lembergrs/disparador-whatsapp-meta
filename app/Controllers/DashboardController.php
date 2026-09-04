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
        $assinaturasAtivas = 0;
        $assinaturasPendentes = 0;
        $assinaturasVencidas = 0;
        $assinaturasCanceladas = 0;
        $metaConta = null;
        $ultimasCampanhas = [];
        $cliente = null;
        $consumo = null;
        $excedente = null;
        $onboardingChecklist = null;
        $avaliacaoDashboard = [];
        $whatsappSuporte = null;

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

            $mensagensRecebidas = $db->query("
                SELECT COUNT(*) total
                FROM conversa_mensagens
                WHERE MSG_Direcao = 'recebida'
            ")->fetch()['total'];

            $assinaturasAtivas = $db->query("
                SELECT COUNT(*) total FROM assinaturas WHERE ASS_Status = 'ativa'
            ")->fetch()['total'];

            $assinaturasPendentes = $db->query("
                SELECT COUNT(*) total FROM assinaturas WHERE ASS_Status = 'pendente'
            ")->fetch()['total'];

            $assinaturasVencidas = $db->query("
                SELECT COUNT(*) total FROM assinaturas WHERE ASS_Status = 'vencida'
            ")->fetch()['total'];

            $assinaturasCanceladas = $db->query("
                SELECT COUNT(*) total FROM assinaturas WHERE ASS_Status = 'cancelada'
            ")->fetch()['total'];

            $ultimasCampanhas = $db->query("
                SELECT *
                FROM campanhas
                ORDER BY CAM_ID DESC
                LIMIT 5
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

            $excedenteModel =
                new \Models\ExcedenteMensal();

            $excedente =
                $excedenteModel->buscarMesAtual(
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

            $operacional = Auth::clienteLiberado();
            $preTrial = Auth::clienteEmPreTrial();
            $gerenciar = in_array($usuario['nivel'], ['cliente', 'cliente_admin'], true);
            $contaContextualId = array_key_exists('conta', $_GET)
                ? (filter_var($_GET['conta'], FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]) ?: 0)
                : null;
            $onboardingChecklist = (new \Services\OnboardingChecklistService())->calcular($cliId, [
                'operacional'=>$operacional,
                'pre_trial'=>$preTrial,
                'gerenciar'=>$gerenciar,
                'configuracao'=>$gerenciar && ($operacional || Auth::clientePodeConectarMeta()),
            ], $contaContextualId);
            $metaConta = $onboardingChecklist['conta'];
            $avaliacaoDashboard = Auth::dadosAvaliacaoCliente(false);
            $whatsappSuporte = (new \Models\ConfiguracaoSite())->obterConfiguracaoWhatsappSite();

            $sql = $db->prepare("
                SELECT *
                FROM campanhas
                WHERE CLI_ID = ?
                ORDER BY CAM_ID DESC
                LIMIT 5
            ");
            $sql->execute([$cliId]);
            $ultimasCampanhas = $sql->fetchAll();

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
                'consumo' => $consumo,
                'excedente' => $excedente,
                'assinaturasAtivas' => $assinaturasAtivas,
                'assinaturasPendentes' => $assinaturasPendentes,
                'assinaturasVencidas' => $assinaturasVencidas,
                'assinaturasCanceladas' => $assinaturasCanceladas,
                'onboardingChecklist' => $onboardingChecklist,
                'avaliacaoDashboard' => $avaliacaoDashboard,
                'whatsappSuporte' => $whatsappSuporte,
                // Compartilha a decisão do Auth com o menu deste mesmo request.
                'acessoOperacionalDashboard' => $operacional ?? null
            ]
        );
    }
}
