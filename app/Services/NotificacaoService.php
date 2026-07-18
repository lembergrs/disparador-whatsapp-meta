<?php

namespace Services;

use Models\Notificacao;

class NotificacaoService
{
    private $config;
    private $canais;
    private $model;

    public function __construct(array $canais = [], $model = null, array $config = null)
    {
        $this->config = $config ?: (require __DIR__ . '/../../config/notificacoes.php');
        $this->model = $model ?: new Notificacao();
        $this->canais = $canais ?: [CanalNotificacao::EMAIL => new EmailService()];
    }

    public function disparar($evento, array $cliente, array $dados = [])
    {
        $contexto = $this->contexto($cliente, $dados);
        $resultados = [];
        foreach($this->config['eventos'][$evento] ?? [] as $canal){
            if(empty($this->canais[$canal])){
                $resultados[$canal] = ['sucesso'=>false,'status'=>'erro_definitivo','error_code'=>'canal_indisponivel'];
                continue;
            }
            $preparado = $canal === CanalNotificacao::EMAIL ? $this->canais[$canal]->preparar($evento, $contexto) : [];
            $notificacaoId = $this->model->criar([
                'cliente_id' => $contexto['cliente_id'],
                'tipo' => $evento,
                'canal' => $canal,
                'assunto' => $preparado['assunto'] ?? null,
                'destino' => $contexto['email'] ?? null,
                'dados' => $dados,
            ]);
            $resultado = $canal === CanalNotificacao::EMAIL
                ? (isset($preparado['sucesso']) && !$preparado['sucesso'] ? $preparado : $this->canais[$canal]->enviar($contexto['email'], $contexto['nome'], $preparado['assunto'], $preparado['html'], $preparado['texto']))
                : ['sucesso'=>false,'status'=>'erro_definitivo','error_code'=>'canal_nao_implementado'];
            $this->model->finalizar($notificacaoId, $resultado);
            $resultados[$canal] = $resultado + ['notificacao_id' => $notificacaoId];
        }
        return ['evento' => $evento, 'resultados' => $resultados];
    }

    private function contexto(array $cliente, array $dados)
    {
        return array_merge([
            'cliente_id' => $cliente['CLI_ID'] ?? null,
            'nome' => $cliente['CLI_Nome'] ?? $cliente['USU_Nome'] ?? $cliente['CLI_NomeFantasia'] ?? 'cliente',
            'empresa' => $cliente['CLI_NomeFantasia'] ?? $cliente['CLI_RazaoSocial'] ?? '',
            'email' => $cliente['CLI_Email'] ?? $cliente['USU_Email'] ?? '',
            'link' => rtrim(BASE_URL, '/') . '/index.php?url=dashboard',
        ], $dados);
    }
}
