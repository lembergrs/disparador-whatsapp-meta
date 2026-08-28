<?php

namespace Services;

use PHPMailer\PHPMailer\PHPMailer;
use Models\NotificacaoModelo;

class EmailService
{
    private $mailerFactory;
    private $config;
    private $modeloRepository;

    public const TEMPLATES = [
        EventoNotificacao::BOAS_VINDAS => ['assunto' => 'Bem-vindo ao Disparador.net', 'titulo' => 'Bem-vindo ao Disparador.net', 'mensagem' => 'Olá, {{NOME}}! Seu cadastro foi concluído. Agora conecte sua conta Meta para começar.', 'botao' => 'Acessar minha conta', 'complemento' => 'O trial começa após a conexão do número do WhatsApp.'],
        EventoNotificacao::META_CONECTADA => ['assunto' => 'Conta Meta conectada', 'titulo' => 'Conta Meta conectada', 'mensagem' => 'Sua conta Meta foi vinculada ao Disparador. Próximo passo: registrar o número e preparar templates.', 'botao' => 'Abrir configurações', 'complemento' => 'Quando novos canais forem ativados, este evento poderá notificá-los sem alteração nos controllers.'],
        EventoNotificacao::TRIAL_3_DIAS => ['assunto' => 'Seu trial termina em 3 dias', 'titulo' => 'Faltam 3 dias de trial', 'mensagem' => 'Seu período de avaliação termina em {{DIAS}} dias. Revise seu plano para continuar usando o Disparador.', 'botao' => 'Ver planos', 'complemento' => 'Mensagens restantes: {{MENSAGENS}}.'],
        EventoNotificacao::TRIAL_ULTIMO_DIA => ['assunto' => 'Último dia do seu trial', 'titulo' => 'Último dia do trial', 'mensagem' => 'Hoje é o último dia do seu período de avaliação.', 'botao' => 'Regularizar plano', 'complemento' => 'Evite interrupções nos seus disparos.'],
        EventoNotificacao::TRIAL_ENCERRADO => ['assunto' => 'Seu trial foi encerrado', 'titulo' => 'Trial encerrado', 'mensagem' => 'Seu período de avaliação terminou em {{DATA}}.', 'botao' => 'Contratar plano', 'complemento' => 'Você pode reativar a conta escolhendo um plano.'],
        EventoNotificacao::PAGAMENTO_APROVADO => ['assunto' => 'Pagamento aprovado', 'titulo' => 'Pagamento aprovado', 'mensagem' => 'Recebemos seu pagamento do plano {{PLANO}}.', 'botao' => 'Abrir financeiro', 'complemento' => 'Obrigado por continuar com o Disparador.'],
        EventoNotificacao::PAGAMENTO_PENDENTE => ['assunto' => 'Pagamento pendente', 'titulo' => 'Pagamento pendente', 'mensagem' => 'Identificamos uma pendência financeira no plano {{PLANO}}.', 'botao' => 'Regularizar pagamento', 'complemento' => 'Regularize para evitar bloqueios comerciais conforme as regras existentes.'],
        EventoNotificacao::CONTA_REATIVADA => ['assunto' => 'Conta reativada', 'titulo' => 'Conta reativada', 'mensagem' => 'Sua conta foi reativada com sucesso.', 'botao' => 'Acessar Disparador', 'complemento' => 'Você já pode retomar suas operações.'],
        EventoNotificacao::COBRANCA_DISPONIVEL => ['assunto'=>'Sua cobrança já está disponível','titulo'=>'Cobrança disponível','mensagem'=>'A cobrança do plano {{PLANO}}, no valor de {{VALOR}}, já está disponível. O vencimento é {{VENCIMENTO}}.','botao'=>'Acessar Financeiro','complemento'=>'Você pode consultar os detalhes e realizar o pagamento pela área Financeiro.'],
        EventoNotificacao::LEMBRETE_VENCIMENTO_D3 => ['assunto'=>'Lembrete de vencimento da sua cobrança','titulo'=>'Vencimento próximo','mensagem'=>'A cobrança do plano {{PLANO}} vence em {{DIAS}} dias, em {{VENCIMENTO}}.','botao'=>'Ver cobrança','complemento'=>'Se o pagamento já foi realizado, aguarde a confirmação automática.'],
        EventoNotificacao::COBRANCA_VENCIDA_D1 => ['assunto'=>'Sua cobrança está em período de tolerância','titulo'=>'Cobrança vencida','mensagem'=>'A cobrança do plano {{PLANO}} venceu há {{DIAS_ATRASO}} dia. Seus recursos continuam disponíveis temporariamente durante o período de tolerância.','botao'=>'Regularizar pagamento','complemento'=>'Acesse o Financeiro para consultar a cobrança e evitar interrupções.'],
        EventoNotificacao::LEMBRETE_VENCIDA_D3 => ['assunto'=>'Lembrete de cobrança em aberto','titulo'=>'Pagamento ainda não identificado','mensagem'=>'A cobrança do plano {{PLANO}} está vencida há {{DIAS_ATRASO}} dias.','botao'=>'Regularizar pagamento','complemento'=>'Se você já pagou, aguarde a confirmação automática.'],
        EventoNotificacao::AVISO_SUSPENSAO_D5 => ['assunto'=>'Aviso sobre possível suspensão em D+7','titulo'=>'Regularize sua cobrança','mensagem'=>'A cobrança do plano {{PLANO}} está vencida há {{DIAS_ATRASO}} dias. Se o pagamento não for identificado, os recursos operacionais serão suspensos em D+7.','botao'=>'Regularizar pagamento','complemento'=>'Seus dados serão preservados e o Financeiro continuará disponível.'],
        EventoNotificacao::SUSPENSAO_INADIMPLENCIA_D7 => ['assunto'=>'Recursos operacionais temporariamente suspensos','titulo'=>'Suspensão por inadimplência','mensagem'=>'Como o pagamento da cobrança do plano {{PLANO}} ainda não foi identificado, os recursos operacionais foram temporariamente suspensos.','botao'=>'Acessar Financeiro','complemento'=>'Seus dados foram preservados. Após a confirmação do pagamento, o acesso será restabelecido automaticamente.'],
        EventoNotificacao::PAGAMENTO_CONFIRMADO => ['assunto'=>'Pagamento confirmado','titulo'=>'Pagamento confirmado','mensagem'=>'Confirmamos o pagamento do plano {{PLANO}}, no valor de {{VALOR}}. {{CONTEXTO_PAGAMENTO}}','botao'=>'Abrir Financeiro','complemento'=>'Obrigado por continuar com o Disparador.net.'],
    ];

    public function __construct(callable $mailerFactory = null, array $config = null, $modeloRepository = null)
    {
        $this->mailerFactory = $mailerFactory;
        $this->config = $config ?: (file_exists(__DIR__ . '/../../config/mail.php') ? require __DIR__ . '/../../config/mail.php' : []);
        $this->modeloRepository = $modeloRepository ?: new NotificacaoModelo();
    }

    public function preparar($evento, array $contexto)
    {
        $tpl = $this->modelo($evento);
        if(!$tpl){
            return ['sucesso' => false, 'status' => 'erro_definitivo', 'error_code' => 'modelo_inexistente', 'mensagem' => 'Modelo inexistente.'];
        }
        $vars = $this->variaveis($contexto);
        $html = $this->renderizarLayout($tpl['titulo'], $tpl['mensagem'], $tpl['botao'], $tpl['complemento'] ?? '', $vars);
        return ['assunto' => $this->substituir($tpl['assunto'], $vars), 'html' => $html, 'texto' => strip_tags(str_replace(['<br>', '<br/>', '<br />'], "
", $html))];
    }

    public function enviarEvento($evento, array $contexto)
    {
        $mensagem = $this->preparar($evento, $contexto);
        if(isset($mensagem['sucesso']) && !$mensagem['sucesso']) return $mensagem;
        return $this->enviar($contexto['email'] ?? '', $contexto['nome'] ?? '', $mensagem['assunto'], $mensagem['html'], $mensagem['texto']);
    }

    public function renderizarLayout($titulo, $mensagem, $botao, $complemento, array $vars)
    {
        $link = htmlspecialchars($vars['{{LINK}}'] ?? rtrim(BASE_URL, '/') . '/index.php?url=dashboard', ENT_QUOTES, 'UTF-8');
        foreach(['titulo','mensagem','botao','complemento'] as $campo){ $$campo = $this->substituir($$campo, $vars); }
        return '<!doctype html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.htmlspecialchars($titulo).'</title></head><body style="margin:0;background:#f4f6f9;font-family:Arial,sans-serif;color:#243447"><table width="100%"><tr><td align="center" style="padding:24px"><table style="max-width:600px;background:#fff;border-radius:12px;overflow:hidden;border:1px solid #e4e9f0"><tr><td style="background:#0d6efd;color:#fff;padding:22px;font-size:24px;font-weight:bold">Disparador.net</td></tr><tr><td style="padding:28px"><h1 style="font-size:22px;margin:0 0 16px">'.htmlspecialchars($titulo).'</h1><p style="font-size:16px;line-height:1.6">'.htmlspecialchars($mensagem).'</p><p style="text-align:center;margin:28px 0"><a href="'.$link.'" style="background:#25d366;color:#073b1a;text-decoration:none;padding:13px 22px;border-radius:6px;font-weight:bold;display:inline-block">'.htmlspecialchars($botao).'</a></p><p style="color:#5f6b7a;line-height:1.5">'.htmlspecialchars($complemento).'</p></td></tr><tr><td style="background:#f8fafc;color:#718096;padding:18px;text-align:center;font-size:12px">Disparador.net · Comunicação transacional do sistema</td></tr></table></td></tr></table></body></html>';
    }

    public function substituir($texto, array $vars) { return strtr((string) $texto, $vars); }

    public function modelo($evento, array $rascunho = null)
    {
        if($rascunho) return $rascunho;
        $personalizado = $this->modeloRepository ? $this->modeloRepository->buscarAtivo($evento, CanalNotificacao::EMAIL) : null;
        if($personalizado){
            return [
                'assunto' => $personalizado['NOM_Assunto'],
                'titulo' => $personalizado['NOM_Titulo'],
                'mensagem' => $personalizado['NOM_Corpo'],
                'botao' => $personalizado['NOM_TextoBotao'] ?: 'Abrir Disparador',
                'complemento' => '',
                'link' => $personalizado['NOM_LinkBotao'] ?: null,
                'personalizado' => true,
            ];
        }
        return self::TEMPLATES[$evento] ?? null;
    }

    public function preview($evento, array $rascunho)
    {
        $tpl = $this->modelo($evento, $rascunho);
        $vars = $this->variaveis($this->dadosPreview());
        $html = $this->renderizarLayout($tpl['titulo'], $tpl['mensagem'], $tpl['botao'], '', $vars);
        return ['assunto'=>$this->substituir($tpl['assunto'], $vars), 'html'=>$html];
    }

    public static function variaveisPorEvento($evento)
    {
        $map = [
            EventoNotificacao::BOAS_VINDAS => ['{{NOME}}'=>'Nome do cliente','{{EMPRESA}}'=>'Nome da empresa','{{EMAIL}}'=>'E-mail do cliente','{{LINK}}'=>'Link principal da ação'],
            EventoNotificacao::META_CONECTADA => ['{{NOME}}'=>'Nome do cliente','{{EMPRESA}}'=>'Nome da empresa','{{LINK}}'=>'Link principal da ação'],
            EventoNotificacao::TRIAL_3_DIAS => ['{{NOME}}'=>'Nome do cliente','{{DIAS}}'=>'Dias restantes','{{MENSAGENS}}'=>'Mensagens restantes','{{PLANO}}'=>'Plano do cliente','{{LINK}}'=>'Link principal da ação'],
            EventoNotificacao::TRIAL_ULTIMO_DIA => ['{{NOME}}'=>'Nome do cliente','{{PLANO}}'=>'Plano do cliente','{{LINK}}'=>'Link principal da ação'],
            EventoNotificacao::TRIAL_ENCERRADO => ['{{NOME}}'=>'Nome do cliente','{{DATA}}'=>'Data do encerramento','{{PLANO}}'=>'Plano do cliente','{{LINK}}'=>'Link principal da ação'],
            EventoNotificacao::PAGAMENTO_APROVADO => ['{{NOME}}'=>'Nome do cliente','{{PLANO}}'=>'Plano do cliente','{{DATA}}'=>'Data do pagamento','{{LINK}}'=>'Link principal da ação'],
            EventoNotificacao::PAGAMENTO_PENDENTE => ['{{NOME}}'=>'Nome do cliente','{{PLANO}}'=>'Plano do cliente','{{DATA}}'=>'Data da pendência','{{LINK}}'=>'Link principal da ação'],
            EventoNotificacao::CONTA_REATIVADA => ['{{NOME}}'=>'Nome do cliente','{{EMPRESA}}'=>'Nome da empresa','{{LINK}}'=>'Link principal da ação'],
            EventoNotificacao::COBRANCA_DISPONIVEL => self::variaveisFinanceiras(),
            EventoNotificacao::LEMBRETE_VENCIMENTO_D3 => self::variaveisFinanceiras(),
            EventoNotificacao::COBRANCA_VENCIDA_D1 => self::variaveisFinanceiras(),
            EventoNotificacao::LEMBRETE_VENCIDA_D3 => self::variaveisFinanceiras(),
            EventoNotificacao::AVISO_SUSPENSAO_D5 => self::variaveisFinanceiras(),
            EventoNotificacao::SUSPENSAO_INADIMPLENCIA_D7 => self::variaveisFinanceiras(),
            EventoNotificacao::PAGAMENTO_CONFIRMADO => self::variaveisFinanceiras(),
        ];
        return $map[$evento] ?? [];
    }

    private static function variaveisFinanceiras()
    {
        return ['{{NOME}}'=>'Nome do cliente','{{EMPRESA}}'=>'Nome da empresa','{{PLANO}}'=>'Plano','{{VALOR}}'=>'Valor','{{VENCIMENTO}}'=>'Vencimento efetivo','{{DIAS}}'=>'Dias para o vencimento','{{DIAS_ATRASO}}'=>'Dias de atraso','{{CONTEXTO_PAGAMENTO}}'=>'Contexto da regularização','{{LINK}}'=>'Link para o Financeiro'];
    }

    public static function placeholdersInvalidos($evento, array $campos)
    {
        preg_match_all('/{{\s*[A-Z0-9_]+\s*}}/', implode(' ', $campos), $m);
        $usados = array_unique(array_map(function($v){ return preg_replace('/\s+/', '', $v); }, $m[0] ?? []));
        return array_values(array_diff($usados, array_keys(self::variaveisPorEvento($evento))));
    }

    private function dadosPreview()
    {
        return ['nome'=>'Rodrigo','empresa'=>'Empresa Exemplo','email'=>'contato@exemplo.com','link'=>'https://disparador.net','plano'=>'Plano Exemplo','data'=>date('d/m/Y'),'dias'=>3,'mensagens'=>200];
    }

    private function variaveis(array $c)
    {
        return ['{{NOME}}'=>$c['nome'] ?? 'cliente','{{EMPRESA}}'=>$c['empresa'] ?? '','{{EMAIL}}'=>$c['email'] ?? '','{{LINK}}'=>$c['link'] ?? rtrim(BASE_URL, '/') . '/index.php?url=dashboard','{{PLANO}}'=>$c['plano'] ?? '','{{DATA}}'=>$c['data'] ?? date('d/m/Y'),'{{DIAS}}'=>(string)($c['dias'] ?? ''),'{{MENSAGENS}}'=>(string)($c['mensagens'] ?? ''),'{{VALOR}}'=>$c['valor'] ?? '','{{VENCIMENTO}}'=>$c['vencimento'] ?? '','{{DIAS_ATRASO}}'=>(string)($c['dias_atraso'] ?? ''),'{{CONTEXTO_PAGAMENTO}}'=>$c['contexto_pagamento'] ?? ''];
    }

    public function enviar($destinatario, $nome, $assunto, $html, $texto)
    {
        if(!filter_var($destinatario, FILTER_VALIDATE_EMAIL)) return ['sucesso'=>false,'status'=>'erro_definitivo','error_code'=>'destinatario_invalido','mensagem'=>'Destinatário inválido.'];
        if(empty($this->config['host']) || empty($this->config['from_address'])) return ['sucesso'=>false,'status'=>'erro_definitivo','error_code'=>'configuracao_email_ausente','mensagem'=>'Configuração de e-mail ausente.'];
        try{
            $m = $this->mailerFactory ? call_user_func($this->mailerFactory) : new PHPMailer(true);
            $m->isSMTP(); $m->Host=$this->config['host']; $m->Port=(int)$this->config['port']; $m->SMTPAuth=!empty($this->config['username']) || !empty($this->config['password']); $m->Username=$this->config['username']; $m->Password=$this->config['password']; $m->SMTPSecure=$this->config['encryption']; $m->Timeout=(int)$this->config['timeout'];
            $m->CharSet='UTF-8'; $m->setFrom($this->config['from_address'], $this->config['from_name']); if(!empty($this->config['reply_to_address'])) $m->addReplyTo($this->config['reply_to_address'], $this->config['reply_to_name']); $m->addAddress($destinatario, $nome ?: $destinatario); $m->Subject=$assunto; $m->Body=$html; $m->AltBody=$texto; $m->isHTML(true); $m->send();
            $this->log('enviado', $destinatario, null); return ['sucesso'=>true,'status'=>'enviada'];
        }catch(\Throwable $e){ $msg=$this->limpar($e->getMessage()); $this->log('erro_smtp', $destinatario, $msg); return ['sucesso'=>false,'status'=>'erro_temporario','error_code'=>'smtp_falha_envio','mensagem'=>$msg]; }
    }

    private function log($status, $destinatario, $erro)
    {
        $dir = dirname(__DIR__, 2) . '/storage/logs'; if(!is_dir($dir)) mkdir($dir, 0770, true);
        error_log(json_encode(['timestamp'=>date('c'),'canal'=>'email','status'=>$status,'destinatario'=>preg_replace('/^(.).*@/', '$1***@', (string)$destinatario),'erro'=>$erro], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL, 3, $dir.'/notificacoes.log');
    }
    private function limpar($m){ return mb_substr(preg_replace('/[\r\n\t]+/', ' ', (string)$m), 0, 255, 'UTF-8'); }
}
