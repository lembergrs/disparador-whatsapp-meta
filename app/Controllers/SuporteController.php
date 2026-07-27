<?php

namespace Controllers;

use Core\Auth;
use Core\Controller;
use Core\Csrf;
use Core\Session;
use Models\SuporteAcesso;

class SuporteController extends Controller
{
    public function iniciar()
    {
        Auth::admin();
        Csrf::exigirPost();

        if(Auth::isImpersonating()){
            Session::flash('error', 'Já existe uma impersonação ativa. Volte para o administrador antes de iniciar outro acesso.');
            $this->redirect('cliente');
        }

        $clienteId = (int) ($_POST['cliente_id'] ?? 0);
        if($clienteId <= 0){
            Session::flash('error', 'Cliente ou usuário inválido.');
            $this->redirect('cliente');
        }

        $auditoriaId = 0;
        try{
            $acessos = new SuporteAcesso();
            $identidade = $acessos->buscarIdentidadePrincipalCliente($clienteId);

            if(!$identidade){
                Session::flash('error', 'Usuário principal ativo não encontrado para este cliente.');
                $this->redirect('cliente');
            }

            $admin = Auth::usuario();
            $auditoriaId = $acessos->iniciar(
                (int) $admin['id'],
                $clienteId,
                (int) $identidade['USU_ID'],
                $_SERVER['REMOTE_ADDR'] ?? '',
                $_SERVER['HTTP_USER_AGENT'] ?? ''
            );

            if($auditoriaId <= 0){
                throw new \RuntimeException('Falha de auditoria.');
            }

            Auth::startImpersonation($identidade, $auditoriaId);
            Session::flash('success', 'Acesso como cliente iniciado com sucesso.');
            $this->redirect('dashboard');
        }catch(\Throwable $e){
            if($auditoriaId > 0){
                try{
                    $acessos->encerrar($auditoriaId, 'outro');
                }catch(\Throwable $auditException){
                    error_log('Erro ao compensar auditoria do modo suporte: ' . $auditException->getMessage());
                }
            }
            error_log('Erro ao iniciar modo suporte: ' . $e->getMessage());
            Session::flash('error', 'Erro ao iniciar o modo suporte.');
            $this->redirect('cliente');
        }
    }

    public function encerrar()
    {
        Csrf::exigirPost();

        if(!Auth::isImpersonating()){
            Session::flash('error', 'Não existe uma impersonação ativa.');
            $this->redirect('dashboard');
        }

        $impersonacao = Auth::impersonacao();
        try{
            (new SuporteAcesso())->encerrar(
                (int) ($impersonacao['auditoria_id'] ?? 0),
                'retorno_normal'
            );
        }catch(\Throwable $e){
            error_log('Erro ao encerrar auditoria do modo suporte: ' . $e->getMessage());
        }

        Auth::stopImpersonation();
        Session::flash('success', 'Retorno ao administrador realizado com sucesso.');
        $this->redirect('cliente');
    }
}
