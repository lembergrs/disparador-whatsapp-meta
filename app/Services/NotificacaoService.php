<?php

namespace Services;

use Models\Notificacao;
use Models\NotificacaoConfiguracao;

class NotificacaoService
{
    private $config;
    private $canais;
    private $model;
    private $configuracaoModel;

    public function __construct(array $canais = [], $model = null, array $config = null, $configuracaoModel = null)
    {
        $this->config = $config ?: (require __DIR__ . '/../../config/notificacoes.php');
        $this->model = $model ?: new Notificacao();
        $this->configuracaoModel = $configuracaoModel ?: new NotificacaoConfiguracao();
        $this->config['eventos'] = $this->configuracaoModel->canaisEfetivos($this->config);
        $this->canais = $canais ?: [CanalNotificacao::EMAIL => new EmailService(), CanalNotificacao::WHATSAPP => new WhatsAppInstitucionalService()];
    }

    public function disparar($evento, array $cliente, array $dados = [])
    {
        $contexto = $this->contexto($cliente, $dados);
        $resultados = [];
        foreach($this->config['eventos'][$evento] ?? [] as $canal){
            try{ $resultados[$canal] = $this->processarCanal($evento, $canal, $contexto, $dados); }
            catch(\Throwable $e){ $resultados[$canal] = ['sucesso'=>false,'status'=>'erro_temporario','error_code'=>'falha_controlada_canal','mensagem'=>'Falha controlada no canal.']; }
        }
        return ['evento' => $evento, 'sucesso'=>!empty($resultados) && !empty(array_filter($resultados, function($r){ return !empty($r['sucesso']); })), 'resultados' => $resultados];
    }

    public function dispararCanal($evento, $canal, array $cliente, array $dados = [])
    {
        if(!in_array($canal, $this->config['eventos'][$evento] ?? [], true)) return ['evento'=>$evento, 'sucesso'=>false, 'resultados'=>[]];
        $contexto = $this->contexto($cliente, $dados);
        try{ $resultado = $this->processarCanal($evento, $canal, $contexto, $dados); }
        catch(\Throwable $e){ $resultado = ['sucesso'=>false,'status'=>'erro_temporario','error_code'=>'falha_controlada_canal','mensagem'=>'Falha controlada no canal.']; }
        return ['evento'=>$evento, 'sucesso'=>!empty($resultado['sucesso']), 'resultados'=>[$canal=>$resultado]];
    }

    public function canaisAtivos($evento): array
    {
        return array_values($this->config['eventos'][$evento] ?? []);
    }

    public function entregarCanalReservado($evento, $canal, array $cliente, array $dados = []): array
    {
        if(!in_array($canal, $this->canaisAtivos($evento), true)){
            return ['sucesso'=>false,'status'=>'erro_definitivo','error_code'=>'canal_desativado','mensagem'=>'Canal desativado para este evento.'];
        }
        if(empty($this->canais[$canal])){
            return ['sucesso'=>false,'status'=>'erro_definitivo','error_code'=>'canal_indisponivel','mensagem'=>'Canal indisponível.'];
        }
        $contexto=$this->contexto($cliente,$dados);
        try{
            if($canal===CanalNotificacao::WHATSAPP){
                $preparado=$this->canais[$canal]->preparar($evento,$contexto);
                return empty($preparado['sucesso'])?$preparado:$this->canais[$canal]->enviarPreparado($preparado);
            }
            $preparado=$this->canais[$canal]->preparar($evento,$contexto);
            return isset($preparado['sucesso'])&&!$preparado['sucesso']?$preparado:$this->canais[$canal]->enviar($contexto['email'],$contexto['nome'],$preparado['assunto'],$preparado['html'],$preparado['texto']);
        }catch(\Throwable $e){
            return ['sucesso'=>false,'status'=>'erro_temporario','error_code'=>'falha_controlada_canal','mensagem'=>'Falha controlada no canal.'];
        }
    }

    private function processarCanal($evento, $canal, array $contexto, array $dados)
    {
        if(empty($this->canais[$canal])) return ['sucesso'=>false,'status'=>'erro_definitivo','error_code'=>'canal_indisponivel'];
        if($canal === CanalNotificacao::WHATSAPP) return $this->processarWhatsApp($evento, $contexto, $dados);
        $preparado = $this->canais[$canal]->preparar($evento, $contexto);
        $id = $this->model->criar(['cliente_id'=>$contexto['cliente_id'], 'tipo'=>$evento, 'canal'=>$canal, 'assunto'=>$preparado['assunto'] ?? null, 'destino'=>$contexto['email'], 'dados'=>$dados]);
        $resultado = isset($preparado['sucesso']) && !$preparado['sucesso'] ? $preparado : $this->canais[$canal]->enviar($contexto['email'], $contexto['nome'], $preparado['assunto'], $preparado['html'], $preparado['texto']);
        $this->model->finalizar($id, $resultado); return $resultado + ['notificacao_id'=>$id];
    }

    private function processarWhatsApp($evento, array $contexto, array $dados)
    {
        $preparado = $this->canais[CanalNotificacao::WHATSAPP]->preparar($evento, $contexto);
        $chave = 'cliente:' . (int)$contexto['cliente_id'] . ':whatsapp:' . $evento;
        $template = $preparado['template'] ?? WhatsAppInstitucionalService::template($evento);
        $destino = $preparado['telefone'] ?? preg_replace('/\D+/', '', (string)($contexto['telefone'] ?? ''));
        $registro = $this->model->reservarIdempotente(['cliente_id'=>$contexto['cliente_id'], 'tipo'=>$evento, 'canal'=>CanalNotificacao::WHATSAPP, 'assunto'=>$template, 'destino'=>$destino, 'template'=>$template, 'chave'=>$chave, 'dados'=>['template'=>$template]]);
        if(!$registro || !$this->model->marcarProcessando((int)$registro['NOT_ID'])) return ['sucesso'=>($registro['NOT_Status'] ?? '') === 'enviada', 'status'=>$registro['NOT_Status'] ?? 'processando', 'error_code'=>'envio_ja_reservado', 'notificacao_id'=>(int)($registro['NOT_ID'] ?? 0)];
        $resultado = empty($preparado['sucesso']) ? $preparado : $this->canais[CanalNotificacao::WHATSAPP]->enviarPreparado($preparado);
        $this->model->finalizarWhatsApp((int)$registro['NOT_ID'], $resultado);
        return $resultado + ['notificacao_id'=>(int)$registro['NOT_ID']];
    }

    public function reenviarEmailAdmin(array $notificacao)
    {
        if(($notificacao['NOT_Canal'] ?? '') !== CanalNotificacao::EMAIL){
            return ['sucesso'=>false,'status'=>'erro_definitivo','error_code'=>'canal_indisponivel','mensagem'=>'Canal ainda não disponível para reenvio.'];
        }
        $contexto = $this->contexto($notificacao, []);
        $contexto['cliente_id'] = $notificacao['CLI_ID'] ?? null;
        $contexto['nome'] = $notificacao['CLI_Nome'] ?? $notificacao['CLI_NomeFantasia'] ?? 'cliente';
        $contexto['empresa'] = $notificacao['CLI_NomeFantasia'] ?? $notificacao['CLI_RazaoSocial'] ?? '';
        $contexto['email'] = $notificacao['NOT_Destino'] ?? $notificacao['CLI_Email'] ?? '';
        if(empty($contexto['cliente_id'])) return ['sucesso'=>false,'status'=>'erro_definitivo','error_code'=>'cliente_indisponivel','mensagem'=>'Cliente não disponível para reenvio.'];
        if(!filter_var($contexto['email'], FILTER_VALIDATE_EMAIL)) return ['sucesso'=>false,'status'=>'erro_definitivo','error_code'=>'destino_invalido','mensagem'=>'Destino inválido para reenvio.'];
        if(!in_array(CanalNotificacao::EMAIL, $this->config['eventos'][$notificacao['NOT_Tipo']] ?? [], true)){
            return ['sucesso'=>false,'status'=>'erro_definitivo','error_code'=>'canal_desativado','mensagem'=>'E-mail desativado para este evento.'];
        }
        return $this->canais[CanalNotificacao::EMAIL]->enviarEvento($notificacao['NOT_Tipo'], $contexto);
    }

    private function contexto(array $cliente, array $dados)
    {
        return array_merge([
            'cliente_id' => $cliente['CLI_ID'] ?? null,
            'nome' => $cliente['CLI_Nome'] ?? $cliente['USU_Nome'] ?? $cliente['CLI_NomeFantasia'] ?? 'cliente',
            'empresa' => $cliente['CLI_NomeFantasia'] ?? $cliente['CLI_RazaoSocial'] ?? '',
            'email' => $cliente['CLI_Email'] ?? $cliente['USU_Email'] ?? '',
            'telefone' => $cliente['CLI_Telefone'] ?? '',
            'link' => rtrim(BASE_URL, '/') . '/index.php?url=dashboard',
        ], $dados);
    }
}
