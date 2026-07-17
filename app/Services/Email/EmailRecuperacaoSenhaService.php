<?php

namespace Services\Email;

use Models\NotificacaoTransacional;

class EmailRecuperacaoSenhaService
{
    private const TIPO = 'email_recuperacao_senha';
    private const CANAL = 'email';
    private const ASSUNTO = 'Recuperação de senha - Disparador.net';

    private $notificacoes;
    private $email;

    public function __construct(?NotificacaoTransacional $notificacoes = null, ?EmailTransacionalService $email = null)
    {
        $this->notificacoes = $notificacoes ?: new NotificacaoTransacional();
        $this->email = $email ?: new EmailTransacionalService();
    }

    public function enviar(array $usuario, $link, $solicitacaoId)
    {
        $usuarioId = (int) ($usuario['USU_ID'] ?? 0);
        $clienteId = (int) ($usuario['CLI_ID'] ?? 0);
        $destinatario = trim((string) ($usuario['USU_Email'] ?? ''));
        $nome = trim((string) ($usuario['USU_Nome'] ?? '')) ?: 'cliente';
        $chave = 'email:recuperacao_senha:solicitacao:' . (int) $solicitacaoId;

        $notificacao = $this->notificacoes->criarPendenteIdempotente([
            'cliente_id' => $clienteId,
            'usuario_id' => $usuarioId,
            'tipo' => self::TIPO,
            'canal' => self::CANAL,
            'destinatario' => $destinatario,
            'assunto' => self::ASSUNTO,
            'chave_idempotencia' => $chave
        ]);

        if(!$notificacao || !$this->notificacoes->marcarProcessando((int) $notificacao['NOT_ID'])){
            return ['sucesso' => false, 'status' => 'erro_temporario', 'error_code' => 'notificacao_nao_processada'];
        }

        $html = $this->html($nome, $link);
        $texto = $this->texto($nome, $link);
        $resultado = $this->email->enviar([
            'destinatario' => $destinatario,
            'nome_destinatario' => $nome,
            'assunto' => self::ASSUNTO,
            'html' => $html,
            'texto' => $texto
        ]);

        $this->notificacoes->marcarResultado((int) $notificacao['NOT_ID'], $resultado);
        $this->registrarLog($clienteId, $usuarioId, $destinatario, $resultado, (int) ($notificacao['NOT_Tentativas'] ?? 0) + 1);

        return $resultado + ['assunto' => self::ASSUNTO, 'html' => $html, 'texto' => $texto];
    }

    private function html($nome, $link)
    {
        $nomeEsc = htmlspecialchars($nome, ENT_QUOTES, 'UTF-8');
        $linkEsc = htmlspecialchars($link, ENT_QUOTES, 'UTF-8');

        return '<!doctype html><html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>' . self::ASSUNTO . '</title></head>'
            . '<body style="margin:0;padding:0;background:#f4f6f9;font-family:Arial,Helvetica,sans-serif;color:#263238;">'
            . '<div style="max-width:600px;margin:0 auto;padding:24px 12px;">'
            . '<div style="background:#ffffff;border-radius:8px;overflow:hidden;border:1px solid #e5e9f0;">'
            . '<div style="background:#0d6efd;color:#ffffff;padding:20px 24px;font-size:22px;font-weight:bold;">Disparador.net</div>'
            . '<div style="padding:24px;line-height:1.55;font-size:15px;">'
            . '<p>Olá, <strong>' . $nomeEsc . '</strong></p>'
            . '<p>Recebemos uma solicitação para redefinir sua senha.</p>'
            . '<p>Clique no botão abaixo e siga as orientações para redefinir sua senha.</p>'
            . '<p style="text-align:center;margin:28px 0;"><a href="' . $linkEsc . '" style="background:#0d6efd;color:#ffffff;text-decoration:none;padding:12px 22px;border-radius:5px;display:inline-block;font-weight:bold;">Redefinir minha senha</a></p>'
            . '<p>Este link é válido por 30 minutos.</p>'
            . '<p>Se você não solicitou esta alteração, basta ignorar este e-mail.</p>'
            . '<p>Equipe Disparador.net</p>'
            . '</div></div></div></body></html>';
    }

    private function texto($nome, $link)
    {
        return "Olá, {$nome}\n\n"
            . "Recebemos uma solicitação para redefinir sua senha.\n\n"
            . "Acesse o link abaixo e siga as orientações para redefinir sua senha:\n\n"
            . "{$link}\n\n"
            . "Este link é válido por 30 minutos.\n\n"
            . "Se você não solicitou esta alteração, basta ignorar este e-mail.\n\n"
            . "Equipe Disparador.net\n";
    }

    private function registrarLog($clienteId, $usuarioId, $destinatario, array $resultado, $tentativas)
    {
        $dir = dirname(__DIR__, 3) . '/storage/logs';
        if(!is_dir($dir)){
            mkdir($dir, 0770, true);
        }

        error_log(json_encode([
            'timestamp' => date('c'),
            'tipo' => self::TIPO,
            'CLI_ID' => (int) $clienteId,
            'USU_ID' => (int) $usuarioId,
            'status' => $resultado['status'] ?? null,
            'tentativas' => (int) $tentativas,
            'destinatario' => $this->mascararEmail($destinatario),
            'codigo' => $resultado['error_code'] ?? null
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL, 3, $dir . '/email-transacional.log');
    }

    private function mascararEmail($email)
    {
        if(strpos((string) $email, '@') === false){
            return '[email-invalido]';
        }
        [$local, $dominio] = explode('@', (string) $email, 2);
        return mb_substr($local, 0, 1, 'UTF-8') . '***@' . $dominio;
    }
}
