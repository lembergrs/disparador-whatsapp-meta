<?php

namespace Controllers;

use Core\Auth;
use Core\Controller;
use Core\Session;
use Models\OnboardingSuporteSolicitacao;

class OnboardingSuporteController extends Controller
{
    public function solicitar()
    {
        Auth::cliente();
        $this->validarCsrfPost();

        if(Auth::isImpersonating()){
            Session::flash('error', 'Solicitações de suporte não podem ser abertas durante o modo suporte administrativo.');
            $this->redirect('dashboard');
        }

        $usuario = Auth::usuario();
        $clienteId = (int) ($usuario['CLI_ID'] ?? 0);
        $contaId = (int) ($_POST['conta_id'] ?? 0);
        $contaId = $contaId > 0 ? $contaId : null;

        try{
            (new OnboardingSuporteSolicitacao())->criar([
                'cliente_id' => $clienteId,
                'usuario_id' => (int) ($usuario['id'] ?? 0),
                'conta_id' => $contaId,
                'etapa' => trim((string) ($_POST['etapa'] ?? '')),
                'assunto' => $_POST['assunto'] ?? '',
                'descricao' => $_POST['descricao'] ?? '',
                'periodo' => $_POST['periodo'] ?? 'qualquer',
                'horario' => $_POST['horario'] ?? ''
            ]);

            Session::flash('success', 'Solicitação de ajuda registrada. Nossa equipe entrará em contato pelo WhatsApp no período informado.');
        }catch(\DomainException | \InvalidArgumentException $e){
            Session::flash('error', $e->getMessage());
        }catch(\Throwable $e){
            error_log('Erro ao registrar suporte de onboarding: ' . $e->getMessage());
            Session::flash('error', 'Não foi possível registrar sua solicitação de ajuda.');
        }

        $retorno = 'dashboard';
        if($contaId){
            $retorno .= '&conta=' . $contaId;
        }
        $this->redirect($retorno);
    }
}
