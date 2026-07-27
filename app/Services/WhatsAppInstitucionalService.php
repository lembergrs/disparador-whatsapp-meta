<?php

namespace Services;

class WhatsAppInstitucionalService
{
    public const TEMPLATES = [
        EventoNotificacao::BOAS_VINDAS => ['nome'=>'boas_vindas_cadastro', 'parametros'=>['nome']],
        EventoNotificacao::CADASTRO_PENDENTE_CONEXAO => ['nome'=>'cadastro_pendente_conexao', 'parametros'=>[]],
        EventoNotificacao::META_CONECTADA => ['nome'=>'conexao_meta_concluida', 'parametros'=>[]],
    ];

    private $config;
    private $transport;

    public function __construct(array $config = null, callable $transport = null)
    {
        $this->config = $config ?: [
            'phone_number_id'=>defined('WHATSAPP_INSTITUCIONAL_PHONE_NUMBER_ID') ? WHATSAPP_INSTITUCIONAL_PHONE_NUMBER_ID : '',
            'waba_id'=>defined('WHATSAPP_INSTITUCIONAL_WABA_ID') ? WHATSAPP_INSTITUCIONAL_WABA_ID : '',
            'access_token'=>defined('WHATSAPP_INSTITUCIONAL_ACCESS_TOKEN') ? WHATSAPP_INSTITUCIONAL_ACCESS_TOKEN : '',
            'api_version'=>defined('WHATSAPP_INSTITUCIONAL_API_VERSION') ? WHATSAPP_INSTITUCIONAL_API_VERSION : 'v23.0',
            'idioma'=>defined('WHATSAPP_INSTITUCIONAL_IDIOMA') ? WHATSAPP_INSTITUCIONAL_IDIOMA : 'pt_BR',
            'timeout'=>defined('WHATSAPP_INSTITUCIONAL_TIMEOUT') ? WHATSAPP_INSTITUCIONAL_TIMEOUT : 15,
        ];
        $this->transport = $transport;
    }

    public static function suporta($evento){ return isset(self::TEMPLATES[$evento]); }
    public static function template($evento){ return self::TEMPLATES[$evento]['nome'] ?? null; }

    public function preparar($evento, array $contexto)
    {
        if(!self::suporta($evento)) return $this->falha('template_nao_suportado', 'Evento sem template institucional.', false);
        $telefone = self::normalizarTelefone($contexto['telefone'] ?? '');
        if(!$telefone) return $this->falha('telefone_invalido', 'Telefone de contato inválido.', false);
        $valores = [];
        foreach(self::TEMPLATES[$evento]['parametros'] as $campo){
            $valor = trim((string) ($contexto[$campo] ?? ''));
            if($valor === '') return $this->falha('parametro_invalido', 'Parâmetro obrigatório ausente.', false);
            $valores[] = $valor;
        }
        return ['sucesso'=>true, 'telefone'=>$telefone, 'template'=>self::template($evento), 'parametros'=>$valores];
    }

    public function enviarPreparado(array $preparado)
    {
        foreach(['phone_number_id','access_token'] as $campo){
            if(trim((string) ($this->config[$campo] ?? '')) === '') return $this->falha('configuracao_ausente', 'Canal institucional indisponível por falta de configuração.', false);
        }
        $componentes = [];
        if(!empty($preparado['parametros'])){
            $params = [];
            foreach($preparado['parametros'] as $valor){ $params[] = ['type'=>'text', 'text'=>(string)$valor]; }
            $componentes[] = ['type'=>'body', 'parameters'=>$params];
        }
        $payload = ['messaging_product'=>'whatsapp', 'to'=>$preparado['telefone'], 'type'=>'template', 'template'=>[
            'name'=>$preparado['template'], 'language'=>['code'=>$this->config['idioma'] ?? 'pt_BR']
        ]];
        if($componentes) $payload['template']['components'] = $componentes;
        $url = 'https://graph.facebook.com/' . ltrim((string)($this->config['api_version'] ?? 'v23.0'), '/') . '/' . rawurlencode((string)$this->config['phone_number_id']) . '/messages';
        try{
            $resposta = $this->transport ? call_user_func($this->transport, $url, $payload, $this->config) : $this->curl($url, $payload);
        }catch(\Throwable $e){ return $this->falha('falha_rede', 'Falha temporária ao contatar a Meta.', true); }
        return $this->interpretar($resposta);
    }

    public function enviarEvento($evento, array $contexto)
    {
        $preparado = $this->preparar($evento, $contexto);
        return empty($preparado['sucesso']) ? $preparado : $this->enviarPreparado($preparado);
    }

    public static function normalizarTelefone($telefone)
    {
        $original = trim((string)$telefone); $digitos = preg_replace('/\D+/', '', $original);
        $internacionalExplicito = strpos($original, '+') === 0;
        if(!$internacionalExplicito && (strlen($digitos) === 10 || strlen($digitos) === 11)) $digitos = '55' . $digitos;
        if(strlen($digitos) < 10 || strlen($digitos) > 15) return null;
        return $digitos;
    }

    public static function mascararTelefone($telefone)
    {
        $digitos = preg_replace('/\D+/', '', (string)$telefone);
        return strlen($digitos) > 6 ? substr($digitos, 0, 2) . str_repeat('*', max(3, strlen($digitos)-6)) . substr($digitos, -4) : '***';
    }

    private function curl($url, array $payload)
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>json_encode($payload), CURLOPT_HTTPHEADER=>['Content-Type: application/json', 'Authorization: Bearer ' . $this->config['access_token']], CURLOPT_CONNECTTIMEOUT=>5, CURLOPT_TIMEOUT=>(int)($this->config['timeout'] ?? 15)]);
        $body = curl_exec($ch); $errno = curl_errno($ch); $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
        if($errno) throw new \RuntimeException('Falha de transporte.');
        return ['http_code'=>$http, 'body'=>$body];
    }

    private function interpretar(array $resposta)
    {
        $http = (int)($resposta['http_code'] ?? 0); $dados = json_decode((string)($resposta['body'] ?? ''), true);
        if($http >= 200 && $http < 300 && is_array($dados)) return ['sucesso'=>true, 'status'=>'enviada', 'message_id'=>$dados['messages'][0]['id'] ?? null];
        if(!is_array($dados)) return $this->falha('resposta_invalida', 'Resposta inválida da Meta.', $http === 0 || $http >= 500);
        $codigo = (string)($dados['error']['code'] ?? ('http_' . $http));
        return $this->falha('meta_' . $codigo, 'A Meta recusou a notificação institucional.', in_array($http, [429,500,502,503,504], true));
    }

    private function falha($codigo, $mensagem, $temporaria)
    {
        return ['sucesso'=>false, 'status'=>$temporaria ? 'erro_temporario' : 'erro_definitivo', 'error_code'=>$codigo, 'mensagem'=>$mensagem];
    }
}
