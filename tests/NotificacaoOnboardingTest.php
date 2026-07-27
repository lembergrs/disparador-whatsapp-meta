<?php

require_once __DIR__ . '/../app/Models/NotificacaoOnboarding.php';
require_once __DIR__ . '/../app/Services/EventoNotificacao.php';
require_once __DIR__ . '/../app/Services/CanalNotificacao.php';
require_once __DIR__ . '/../app/Services/NotificacaoOnboardingProcessor.php';

use Models\NotificacaoOnboarding;
use Services\CanalNotificacao;
use Services\NotificacaoOnboardingProcessor;

function onboardingAssert($cond, $msg){ if(!$cond){ fwrite(STDERR, "FAIL: {$msg}\n"); exit(1); } }
$agora = new DateTimeImmutable('2026-07-28 10:00:00');
$base = ['CLI_Ativo'=>'S','CLI_DataCadastro'=>'2026-07-27 10:00:00','CLI_DataLiberacao'=>null,'meta_conectada'=>false];
onboardingAssert(NotificacaoOnboarding::elegivelPorDados($base, $agora), 'exatamente 24 horas deve ser elegível');
onboardingAssert(NotificacaoOnboarding::elegivelPorDados(array_merge($base,['CLI_DataCadastro'=>'2026-07-26 09:00:00']), $agora), 'mais de 24 horas deve ser elegível');
onboardingAssert(!NotificacaoOnboarding::elegivelPorDados(array_merge($base,['CLI_DataCadastro'=>'2026-07-27 10:01:00']), $agora), 'menos de 24 horas não deve ser elegível');
onboardingAssert(!NotificacaoOnboarding::elegivelPorDados(array_merge($base,['CLI_Ativo'=>'N']), $agora), 'cliente inativo não deve ser elegível');
onboardingAssert(!NotificacaoOnboarding::elegivelPorDados(array_merge($base,['meta_conectada'=>true]), $agora), 'cliente conectado não deve ser elegível');
onboardingAssert(!NotificacaoOnboarding::elegivelPorDados(array_merge($base,['CLI_DataLiberacao'=>'2026-07-28']), $agora), 'trial iniciado não deve ser elegível');

class CandidatosFake {
    public $continua = [1=>true,2=>true,3=>false];
    public function listarPendentesConexao($ultimo,$limite){ return $ultimo ? [] : [['CLI_ID'=>1],['CLI_ID'=>2],['CLI_ID'=>3]]; }
    public function continuaSemConexao($id){ return $this->continua[$id]; }
}
class CentralFake {
    public $ids=[];
    public function disparar($evento,array $cliente){ $this->ids[]=$cliente['CLI_ID']; if($cliente['CLI_ID']===1) throw new RuntimeException('falha'); return ['resultados'=>[CanalNotificacao::WHATSAPP=>['sucesso'=>true]]]; }
}
$central = new CentralFake(); $resumo = (new NotificacaoOnboardingProcessor(new CandidatosFake(), $central))->executar(100);
onboardingAssert($resumo === ['avaliados'=>3,'enviados'=>1,'ignorados'=>1,'falhas'=>1], 'lote deve continuar após falha e revalidar conexão');
onboardingAssert($central->ids === [1,2], 'cliente conectado durante processamento não deve ser enviado');

echo "NotificacaoOnboardingTest OK\n";
