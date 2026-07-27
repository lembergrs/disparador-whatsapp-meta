<?php

namespace Services;

use Models\NotificacaoOnboarding;

class NotificacaoOnboardingProcessor
{
    private $candidatos;
    private $notificacoes;
    public function __construct($candidatos = null, $notificacoes = null){ $this->candidatos = $candidatos ?: new NotificacaoOnboarding(); $this->notificacoes = $notificacoes ?: new NotificacaoService(); }

    public function executar($limite = 100)
    {
        $resumo = ['avaliados'=>0, 'enviados'=>0, 'ignorados'=>0, 'falhas'=>0]; $ultimoId = 0;
        do{
            $lote = $this->candidatos->listarPendentesConexao($ultimoId, $limite);
            foreach($lote as $cliente){
                $ultimoId = max($ultimoId, (int)$cliente['CLI_ID']); $resumo['avaliados']++;
                try{
                    if(!$this->candidatos->continuaSemConexao((int)$cliente['CLI_ID'])){ $resumo['ignorados']++; continue; }
                    $resultado = $this->notificacoes->disparar(EventoNotificacao::CADASTRO_PENDENTE_CONEXAO, $cliente);
                    $whatsapp = $resultado['resultados'][CanalNotificacao::WHATSAPP] ?? null;
                    if(!$whatsapp){ $resumo['ignorados']++; }
                    elseif(!empty($whatsapp['sucesso'])){ $resumo['enviados']++; }
                    else{ $resumo['falhas']++; }
                }catch(\Throwable $e){ $resumo['falhas']++; }
            }
        }while(count($lote) === (int)$limite);
        return $resumo;
    }
}
