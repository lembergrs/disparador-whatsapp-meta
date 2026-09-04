<?php

namespace Controllers;

use Core\Auth;
use Core\Controller;
use Core\Session;
use Models\OnboardingSuporteSolicitacao;

class OnboardingSuporteAdminController extends Controller
{
    public function index()
    {
        Auth::admin();

        $status = $_GET['status'] ?? null;
        if($status && !in_array($status, OnboardingSuporteSolicitacao::STATUS, true)){
            $status = null;
        }

        $this->view('onboarding_suporte_admin/index', [
            'titulo' => 'Suporte de onboarding',
            'solicitacoes' => (new OnboardingSuporteSolicitacao())->listarAdmin($status),
            'statusFiltro' => $status
        ]);
    }

    public function alterarStatus()
    {
        Auth::admin();
        $this->validarCsrfPost();

        try{
            (new OnboardingSuporteSolicitacao())->atualizarStatus(
                (int) ($_POST['id'] ?? 0),
                (string) ($_POST['status'] ?? ''),
                (int) (Auth::usuario()['id'] ?? 0)
            );
            Session::flash('success', 'Situação do atendimento atualizada.');
        }catch(\InvalidArgumentException $e){
            Session::flash('error', $e->getMessage());
        }catch(\Throwable $e){
            error_log('Erro ao atualizar suporte de onboarding: ' . $e->getMessage());
            Session::flash('error', 'Não foi possível atualizar a solicitação.');
        }

        $this->redirect('onboardingSuporteAdmin');
    }
}
