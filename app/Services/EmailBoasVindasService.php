<?php

namespace Services;

use Models\NotificacaoTransacional;

class EmailBoasVindasService
{
    private const TIPO = 'boas_vindas';
    private const CANAL = 'email';
    private const ASSUNTO = 'Bem-vindo ao Disparador.net — veja os próximos passos';

    private $notificacoes;
    private $email;

    public function __construct(?NotificacaoTransacional $notificacoes = null, ?EmailTransacionalService $email = null)
    {
        $this->notificacoes = $notificacoes ?: new NotificacaoTransacional();
        $this->email = $email ?: new EmailTransacionalService();
    }

    public function enviarParaCadastro(array $cliente, array $usuario = [])
    {
        $clienteId = (int) ($cliente['CLI_ID'] ?? 0);
        $usuarioId = (int) ($usuario['USU_ID'] ?? 0);
        $destinatario = trim((string) ($usuario['USU_Email'] ?? $cliente['CLI_Email'] ?? ''));
        $nome = $this->nomeDestinatario($cliente, $usuario);
        $chave = $this->chaveIdempotencia($clienteId);
        $loginUrl = $this->loginUrl();

        $notificacao = $this->notificacoes->criarPendenteIdempotente([
            'cliente_id' => $clienteId,
            'usuario_id' => $usuarioId ?: null,
            'tipo' => self::TIPO,
            'canal' => self::CANAL,
            'destinatario' => $destinatario,
            'assunto' => self::ASSUNTO,
            'chave_idempotencia' => $chave
        ]);

        if(!$notificacao){
            $resultado = ['sucesso' => false, 'status' => 'erro_temporario', 'error_code' => 'notificacao_nao_persistida', 'mensagem' => 'Não foi possível registrar a notificação.'];
            $this->registrarLog($clienteId, $usuarioId, $destinatario, $resultado, 0);
            return $resultado;
        }

        if(in_array($notificacao['NOT_Status'] ?? '', [NotificacaoTransacional::STATUS_ENVIADO, NotificacaoTransacional::STATUS_PROCESSANDO], true) || (int) ($notificacao['NOT_Tentativas'] ?? 0) > 0){
            $resultado = ['sucesso' => ($notificacao['NOT_Status'] ?? '') === NotificacaoTransacional::STATUS_ENVIADO, 'status' => $notificacao['NOT_Status'] ?? 'pendente', 'error_code' => 'envio_ja_registrado', 'mensagem' => 'Envio já registrado para este cadastro.'];
            $this->registrarLog($clienteId, $usuarioId, $destinatario, $resultado, (int) ($notificacao['NOT_Tentativas'] ?? 0));
            return $resultado;
        }

        if(!$this->notificacoes->marcarProcessando((int) $notificacao['NOT_ID'])){
            $resultado = ['sucesso' => false, 'status' => 'processando', 'error_code' => 'notificacao_em_processamento', 'mensagem' => 'Notificação em processamento.'];
            $this->registrarLog($clienteId, $usuarioId, $destinatario, $resultado, (int) ($notificacao['NOT_Tentativas'] ?? 0));
            return $resultado;
        }

        $html = $this->html($nome, $loginUrl);
        $texto = $this->texto($nome, $loginUrl);

        $resultado = $this->email->enviar([
            'destinatario' => $destinatario,
            'nome_destinatario' => $nome,
            'assunto' => self::ASSUNTO,
            'html' => $html,
            'texto' => $texto
        ]);

        $this->notificacoes->marcarResultado((int) $notificacao['NOT_ID'], $resultado);
        $this->registrarLog($clienteId, $usuarioId, $destinatario, $resultado, (int) ($notificacao['NOT_Tentativas'] ?? 0) + 1);

        return $resultado + ['html' => $html, 'texto' => $texto, 'assunto' => self::ASSUNTO, 'login_url' => $loginUrl];
    }

    public function chaveIdempotencia($clienteId)
    {
        return 'email:boas_vindas:cliente:' . (int) $clienteId;
    }

    private function nomeDestinatario(array $cliente, array $usuario)
    {
        foreach([$usuario['USU_Nome'] ?? null, $cliente['CLI_Nome'] ?? null, $cliente['CLI_NomeFantasia'] ?? null, $cliente['CLI_RazaoSocial'] ?? null] as $nome){
            $nome = trim((string) $nome);
            if($nome !== ''){
                return $nome;
            }
        }

        return 'cliente';
    }

    private function loginUrl()
    {
        return rtrim((string) BASE_URL, '/') . '/index.php?url=login';
    }

    private function html($nome, $loginUrl)
    {
        $nomeEsc = htmlspecialchars($nome, ENT_QUOTES, 'UTF-8');
        $urlEsc = htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8');

        return '<!doctype html><html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>' . self::ASSUNTO . '</title></head>'
            . '<body style="margin:0;padding:0;background:#f4f6f9;font-family:Arial,Helvetica,sans-serif;color:#263238;">'
            . '<div style="max-width:600px;margin:0 auto;padding:24px 12px;">'
            . '<div style="background:#ffffff;border-radius:8px;overflow:hidden;border:1px solid #e5e9f0;">'
            . '<div style="background:#0d6efd;color:#ffffff;padding:20px 24px;font-size:22px;font-weight:bold;">Disparador.net</div>'
            . '<div style="padding:24px;line-height:1.55;font-size:15px;">'
            . '<p>Olá, <strong>' . $nomeEsc . '</strong>!</p>'
            . '<p>Seja bem-vindo ao Disparador.net.</p>'
            . '<p>Seu cadastro foi concluído com sucesso. Agora faltam apenas algumas etapas para conectar seu número do WhatsApp e começar a utilizar a plataforma.</p>'
            . '<h2 style="font-size:18px;color:#0d3b66;margin-top:24px;">Próximos passos</h2>'
            . '<ol style="padding-left:20px;">'
            . '<li><strong>Acesse sua conta.</strong><br>Entre no Disparador.net usando o e-mail e a senha cadastrados.</li>'
            . '<li><strong>Inicie a conexão com a Meta.</strong><br>No sistema, acesse a área de configuração da Meta e inicie a conexão do seu número do WhatsApp.</li>'
            . '<li><strong>Entre com um administrador da Meta.</strong><br>Durante o processo, utilize uma conta do Facebook que possua permissão de administrador no Portfólio Empresarial da empresa.</li>'
            . '<li><strong>Selecione ou cadastre sua empresa e o WhatsApp.</strong><br>Siga as etapas apresentadas pela Meta para selecionar ou criar o Portfólio Empresarial, a conta do WhatsApp Business e o número que será conectado.</li>'
            . '<li><strong>Confirme o número.</strong><br>Tenha acesso ao número de telefone para receber o código de confirmação quando solicitado pela Meta.</li>'
            . '<li><strong>Comece a preparar seus envios.</strong><br>Depois da conexão, você poderá sincronizar ou cadastrar templates, importar contatos e preparar seus primeiros disparos.</li>'
            . '</ol>'
            . '<p style="background:#fff8e1;border-left:4px solid #ffc107;padding:12px 14px;"><strong>Importante:</strong> o período de avaliação começa somente após a conexão do número do WhatsApp, conforme as condições apresentadas na plataforma.</p>'
            . '<p style="text-align:center;margin:28px 0;"><a href="' . $urlEsc . '" style="background:#0d6efd;color:#ffffff;text-decoration:none;padding:12px 22px;border-radius:5px;display:inline-block;font-weight:bold;">Acessar o Disparador.net</a></p>'
            . '<h2 style="font-size:18px;color:#0d3b66;">Antes de começar, tenha em mãos</h2>'
            . '<ul style="padding-left:20px;"><li>acesso ao Facebook do administrador da empresa;</li><li>acesso ao Portfólio Empresarial da Meta, caso já exista;</li><li>dados cadastrais da empresa;</li><li>acesso ao número que será conectado;</li><li>possibilidade de receber o código nesse número.</li></ul>'
            . '<p>Caso tenha alguma dificuldade, responda a este e-mail ou entre em contato com nosso suporte.</p>'
            . '<p>Atenciosamente,<br>Equipe Disparador.net<br>RL2 Net</p>'
            . '</div></div></div></body></html>';
    }

    private function texto($nome, $loginUrl)
    {
        return "Olá, {$nome}!\n\n"
            . "Seja bem-vindo ao Disparador.net.\n\n"
            . "Seu cadastro foi concluído com sucesso. Agora faltam apenas algumas etapas para conectar seu número do WhatsApp e começar a utilizar a plataforma.\n\n"
            . "PRÓXIMOS PASSOS\n"
            . "1. Acesse sua conta usando o e-mail e a senha cadastrados.\n"
            . "2. No sistema, acesse a área de configuração da Meta e inicie a conexão do seu número do WhatsApp.\n"
            . "3. Utilize uma conta do Facebook com permissão de administrador no Portfólio Empresarial da empresa.\n"
            . "4. Siga as etapas da Meta para selecionar ou criar o Portfólio Empresarial, a conta do WhatsApp Business e o número que será conectado.\n"
            . "5. Tenha acesso ao número de telefone para receber o código de confirmação quando solicitado.\n"
            . "6. Depois da conexão, sincronize ou cadastre templates, importe contatos e prepare seus primeiros disparos.\n\n"
            . "IMPORTANTE\nO período de avaliação começa somente após a conexão do número do WhatsApp, conforme as condições apresentadas na plataforma.\n\n"
            . "Acessar o Disparador.net: {$loginUrl}\n\n"
            . "ANTES DE COMEÇAR, TENHA EM MÃOS\n- acesso ao Facebook do administrador da empresa;\n- acesso ao Portfólio Empresarial da Meta, caso já exista;\n- dados cadastrais da empresa;\n- acesso ao número que será conectado;\n- possibilidade de receber o código nesse número.\n\n"
            . "Caso tenha alguma dificuldade, responda a este e-mail ou entre em contato com nosso suporte.\n\n"
            . "Atenciosamente,\nEquipe Disparador.net\nRL2 Net\n";
    }

    private function registrarLog($clienteId, $usuarioId, $destinatario, array $resultado, $tentativas)
    {
        $dir = dirname(__DIR__, 2) . '/storage/logs';
        if(!is_dir($dir)){
            mkdir($dir, 0770, true);
        }

        $linha = [
            'timestamp' => date('c'),
            'tipo' => self::TIPO,
            'CLI_ID' => (int) $clienteId,
            'USU_ID' => $usuarioId ?: null,
            'status' => $resultado['status'] ?? null,
            'tentativas' => (int) $tentativas,
            'destinatario' => $this->mascararEmail($destinatario),
            'codigo' => $resultado['error_code'] ?? null
        ];

        error_log(json_encode($linha, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL, 3, $dir . '/email-transacional.log');
    }

    private function mascararEmail($email)
    {
        $email = trim((string) $email);
        if(strpos($email, '@') === false){
            return '[email-invalido]';
        }
        [$local, $dominio] = explode('@', $email, 2);
        return mb_substr($local, 0, 1, 'UTF-8') . '***@' . $dominio;
    }
}
