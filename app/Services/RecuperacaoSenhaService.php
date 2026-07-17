<?php

namespace Services;

use Core\Database;
use Models\RecuperacaoSenha;
use Services\Email\EmailRecuperacaoSenhaService;

class RecuperacaoSenhaService
{
    private const MINUTOS_VALIDADE = 30;
    private const MENSAGEM_PUBLICA = 'Solicitação recebida. Verifique sua caixa de entrada e também a pasta de spam para conferir as instruções de redefinição de senha.';

    private $model;
    private $email;
    private $db;

    public function __construct(?RecuperacaoSenha $model = null, ?EmailRecuperacaoSenhaService $email = null, $db = null)
    {
        $this->db = $db ?: Database::getInstance();
        $this->model = $model ?: new RecuperacaoSenha($this->db);
        $this->email = $email ?: new EmailRecuperacaoSenhaService();
    }

    public function solicitar($email, $ip = null, $userAgent = null)
    {
        $email = trim((string) $email);
        $publico = ['sucesso' => true, 'mensagem_publica' => self::MENSAGEM_PUBLICA];

        if(!filter_var($email, FILTER_VALIDATE_EMAIL)){
            return $publico + ['motivo_interno' => 'email_invalido'];
        }

        if($this->model->contarRecentesPorIp((string) $ip, 5) >= 5){
            return $publico + ['motivo_interno' => 'rate_limit_ip'];
        }

        $usuario = $this->model->buscarUsuarioRecuperavelPorEmail($email);
        if(!$usuario){
            return $publico + ['motivo_interno' => 'usuario_nao_recuperavel'];
        }

        if($this->model->contarRecentesPorUsuario((int) $usuario['USU_ID'], 3) >= 1){
            return $publico + ['motivo_interno' => 'rate_limit_email'];
        }

        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $expiraEm = date('Y-m-d H:i:s', time() + (self::MINUTOS_VALIDADE * 60));

        try{
            $this->db->beginTransaction();
            $this->model->invalidarPendentesUsuario((int) $usuario['USU_ID']);
            $recuperacaoId = $this->model->criar((int) $usuario['USU_ID'], $tokenHash, $expiraEm, $ip, $userAgent);
            $this->db->commit();
        }catch(\Throwable $e){
            if($this->db->inTransaction()){
                $this->db->rollBack();
            }
            return $publico + ['motivo_interno' => 'falha_persistencia'];
        }

        $link = rtrim((string) BASE_URL, '/') . '/index.php?url=login/redefinirSenha&token=' . urlencode($token);
        $resultadoEmail = $this->email->enviar($usuario, $link, $recuperacaoId);

        return $publico + [
            'email_tentado' => true,
            'email_enviado' => !empty($resultadoEmail['sucesso']),
            'recuperacao_id' => $recuperacaoId
        ];
    }

    public function validarToken($token)
    {
        $tokenHash = $this->hashTokenRecebido($token);
        if($tokenHash === null){
            return ['valido' => false, 'motivo' => 'formato_invalido'];
        }

        $solicitacao = $this->model->buscarPorHash($tokenHash);
        if(!$this->solicitacaoValida($solicitacao)){
            return ['valido' => false, 'motivo' => 'indisponivel'];
        }

        return ['valido' => true, 'solicitacao' => $solicitacao];
    }

    public function redefinir($token, $novaSenha, $confirmacao)
    {
        if((string) $novaSenha !== (string) $confirmacao){
            return ['sucesso' => false, 'tipo' => 'validacao', 'mensagem' => 'As senhas informadas não conferem.'];
        }

        if(!SenhaForteValidator::forte((string) $novaSenha)){
            return ['sucesso' => false, 'tipo' => 'validacao', 'mensagem' => SenhaForteValidator::mensagem()];
        }

        $tokenHash = $this->hashTokenRecebido($token);
        if($tokenHash === null){
            return ['sucesso' => false, 'tipo' => 'token', 'mensagem' => 'Este link de redefinição não está mais disponível.'];
        }

        try{
            $this->db->beginTransaction();
            $solicitacao = $this->model->buscarPorHash($tokenHash, true);
            if(!$this->solicitacaoValida($solicitacao)){
                $this->db->rollBack();
                return ['sucesso' => false, 'tipo' => 'token', 'mensagem' => 'Este link de redefinição não está mais disponível.'];
            }

            $senhaHash = password_hash((string) $novaSenha, PASSWORD_DEFAULT);
            if(!$this->model->atualizarSenhaUsuario((int) $solicitacao['USU_ID'], $senhaHash)){
                $this->db->rollBack();
                return ['sucesso' => false, 'tipo' => 'token', 'mensagem' => 'Este link de redefinição não está mais disponível.'];
            }

            if(!$this->model->marcarUtilizado((int) $solicitacao['RSE_ID'])){
                $this->db->rollBack();
                return ['sucesso' => false, 'tipo' => 'token', 'mensagem' => 'Este link de redefinição não está mais disponível.'];
            }

            $this->model->invalidarOutrosPendentes((int) $solicitacao['USU_ID'], (int) $solicitacao['RSE_ID']);
            $this->db->commit();
            return ['sucesso' => true];
        }catch(\Throwable $e){
            if($this->db->inTransaction()){
                $this->db->rollBack();
            }
            return ['sucesso' => false, 'tipo' => 'token', 'mensagem' => 'Este link de redefinição não está mais disponível.'];
        }
    }

    private function hashTokenRecebido($token)
    {
        $token = trim((string) $token);
        if(strlen($token) !== 64 || !ctype_xdigit($token)){
            return null;
        }
        return hash('sha256', $token);
    }

    private function solicitacaoValida($solicitacao)
    {
        if(!$solicitacao || ($solicitacao['USU_Ativo'] ?? '') !== 'S'){
            return false;
        }
        if(!empty($solicitacao['RSE_UtilizadoEm']) || !empty($solicitacao['RSE_InvalidadoEm'])){
            return false;
        }
        $expira = strtotime((string) ($solicitacao['RSE_ExpiraEm'] ?? ''));
        return $expira !== false && $expira >= time();
    }

    public static function mensagemPublicaSolicitacao()
    {
        return self::MENSAGEM_PUBLICA;
    }
}
