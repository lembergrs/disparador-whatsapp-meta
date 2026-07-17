<?php

namespace Services\Email;

class EmailTransacionalService
{
    private $mailerFactory;

    public function __construct(callable $mailerFactory = null)
    {
        $this->mailerFactory = $mailerFactory;
    }

    public function enviar(array $mensagem)
    {
        $destinatario = trim((string) ($mensagem['destinatario'] ?? ''));
        $nomeDestinatario = trim((string) ($mensagem['nome_destinatario'] ?? ''));
        $assunto = trim((string) ($mensagem['assunto'] ?? ''));
        $html = (string) ($mensagem['html'] ?? '');
        $texto = (string) ($mensagem['texto'] ?? '');

        if(!filter_var($destinatario, FILTER_VALIDATE_EMAIL) || $this->contemQuebraLinha($destinatario)){
            return $this->falha('erro_definitivo', 'destinatario_invalido', 'Destinatário inválido.');
        }

        if($assunto === '' || $html === '' || $texto === ''){
            return $this->falha('erro_definitivo', 'mensagem_incompleta', 'Mensagem transacional incompleta.');
        }

        $config = $this->configuracao();
        $validacao = $this->validarConfiguracao($config);
        if($validacao !== null){
            return $validacao;
        }

        try{
            $mailer = $this->criarMailer();
            $this->configurarMailer($mailer, $config);

            $mailer->CharSet = 'UTF-8';
            $mailer->Subject = $assunto;
            $mailer->Body = $html;
            $mailer->AltBody = $texto;
            $mailer->isHTML(true);
            $mailer->setFrom($config['from_address'], $config['from_name']);
            if($config['reply_to_address'] !== ''){
                $mailer->addReplyTo($config['reply_to_address'], $config['reply_to_name']);
            }
            $mailer->addAddress($destinatario, $nomeDestinatario !== '' ? $nomeDestinatario : $destinatario);
            $mailer->send();

            return ['sucesso' => true, 'status' => 'enviado', 'error_code' => null, 'mensagem' => null];
        }catch(\Throwable $e){
            return $this->classificarExcecao($e);
        }
    }

    private function configuracao()
    {
        return [
            'host' => defined('MAIL_HOST') ? trim((string) MAIL_HOST) : '',
            'port' => defined('MAIL_PORT') ? (int) MAIL_PORT : 587,
            'username' => defined('MAIL_USERNAME') ? (string) MAIL_USERNAME : '',
            'password' => defined('MAIL_PASSWORD') ? (string) MAIL_PASSWORD : '',
            'encryption' => defined('MAIL_ENCRYPTION') ? strtolower(trim((string) MAIL_ENCRYPTION)) : 'tls',
            'from_address' => defined('MAIL_FROM_ADDRESS') ? trim((string) MAIL_FROM_ADDRESS) : '',
            'from_name' => defined('MAIL_FROM_NAME') ? trim((string) MAIL_FROM_NAME) : 'Disparador.net',
            'reply_to_address' => defined('MAIL_REPLY_TO_ADDRESS') ? trim((string) MAIL_REPLY_TO_ADDRESS) : '',
            'reply_to_name' => defined('MAIL_REPLY_TO_NAME') ? trim((string) MAIL_REPLY_TO_NAME) : 'Suporte Disparador.net',
            'timeout' => defined('MAIL_TIMEOUT') ? max(1, (int) MAIL_TIMEOUT) : 10,
        ];
    }

    private function validarConfiguracao(array $config)
    {
        if($config['host'] === '' || $config['from_address'] === ''){
            return $this->falha('erro_definitivo', 'configuracao_email_ausente', 'Configuração de e-mail ausente.');
        }

        if(!filter_var($config['from_address'], FILTER_VALIDATE_EMAIL) || $this->contemQuebraLinha($config['from_address'])){
            return $this->falha('erro_definitivo', 'remetente_invalido', 'Remetente inválido.');
        }

        if($config['reply_to_address'] !== '' && (!filter_var($config['reply_to_address'], FILTER_VALIDATE_EMAIL) || $this->contemQuebraLinha($config['reply_to_address']))){
            return $this->falha('erro_definitivo', 'reply_to_invalido', 'Reply-To inválido.');
        }

        if(!in_array($config['encryption'], ['tls', 'ssl', 'smtps', ''], true)){
            return $this->falha('erro_definitivo', 'criptografia_invalida', 'Criptografia de e-mail inválida.');
        }

        return null;
    }

    private function criarMailer()
    {
        if($this->mailerFactory){
            return call_user_func($this->mailerFactory);
        }

        if(!class_exists('PHPMailer\\PHPMailer\\PHPMailer')){
            throw new \RuntimeException('PHPMailer indisponível.');
        }

        $classe = 'PHPMailer\\PHPMailer\\PHPMailer';
        return new $classe(true);
    }

    private function configurarMailer($mailer, array $config)
    {
        $mailer->isSMTP();
        $mailer->Host = $config['host'];
        $mailer->Port = $config['port'];
        $mailer->Timeout = $config['timeout'];
        $mailer->SMTPAuth = $config['username'] !== '' || $config['password'] !== '';
        $mailer->Username = $config['username'];
        $mailer->Password = $config['password'];

        if($config['encryption'] === 'ssl' || $config['encryption'] === 'smtps'){
            $mailer->SMTPSecure = 'ssl';
        }elseif($config['encryption'] === 'tls'){
            $mailer->SMTPSecure = 'tls';
        }else{
            $mailer->SMTPSecure = '';
        }
    }

    private function classificarExcecao(\Throwable $e)
    {
        $mensagem = $this->sanitizarMensagem($e->getMessage());
        $lower = strtolower($mensagem);
        $temporario = strpos($lower, 'timeout') !== false
            || strpos($lower, 'timed out') !== false
            || strpos($lower, 'connection') !== false
            || strpos($lower, 'could not connect') !== false
            || strpos($lower, 'tempor') !== false;

        return $this->falha(
            $temporario ? 'erro_temporario' : 'erro_definitivo',
            $temporario ? 'smtp_temporariamente_indisponivel' : 'smtp_falha_envio',
            $mensagem !== '' ? $mensagem : 'Falha controlada no envio de e-mail.'
        );
    }

    private function falha($status, $codigo, $mensagem)
    {
        return ['sucesso' => false, 'status' => $status, 'error_code' => $codigo, 'mensagem' => $this->sanitizarMensagem($mensagem)];
    }

    private function contemQuebraLinha($valor)
    {
        return preg_match('/[\r\n]/', (string) $valor) === 1;
    }

    private function sanitizarMensagem($mensagem)
    {
        $mensagem = preg_replace('/[\r\n\t]+/', ' ', trim((string) $mensagem));
        $mensagem = preg_replace('/(password|senha|token|secret|authorization)\s*[:=]\s*\S+/i', '$1=[removido]', $mensagem);
        return mb_substr($mensagem, 0, 255, 'UTF-8');
    }
}
