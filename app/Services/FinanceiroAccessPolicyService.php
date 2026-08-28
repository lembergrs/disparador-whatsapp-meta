<?php

namespace Services;

use Models\Assinatura;
use Models\Cobranca;

class FinanceiroAccessPolicyService
{
    private $assinaturas;
    private $cobrancas;
    private $agora;
    private $logger;
    private $diasTolerancia;

    public function __construct($assinaturas = null, $cobrancas = null, callable $agora = null, callable $logger = null, $diasTolerancia = null)
    {
        $this->assinaturas = $assinaturas ?: new Assinatura();
        $this->cobrancas = $cobrancas ?: new Cobranca();
        $this->agora = $agora ?: function(){ return new \DateTimeImmutable('today'); };
        $this->logger = $logger;
        $this->diasTolerancia = max(1, (int) ($diasTolerancia ?? (defined('FINANCEIRO_DIAS_TOLERANCIA_VENCIMENTO') ? FINANCEIRO_DIAS_TOLERANCIA_VENCIMENTO : 7)));
    }

    public function avaliar(int $clienteId): array
    {
        $assinatura = $this->assinaturas->buscarAtivaPorCliente($clienteId);
        if(!$assinatura){ return $this->resultado('sem_vinculo', false, null, null, 0); }

        $hoje = $this->dataAtual();
        $cobranca = $this->cobrancas->buscarObrigacaoVencidaPorAssinatura($clienteId, (int) $assinatura['ASS_ID'], $hoje->format('Y-m-d'));
        if(!$cobranca){ return $this->resultado('regular', true, $assinatura, null, 0); }

        $vencimento = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) ($cobranca['COB_VencimentoFinanceiro'] ?? ''));
        if(!$vencimento){ throw new \RuntimeException('Cobrança com vencimento financeiro inválido.'); }
        $diasAtraso = (int) $vencimento->diff($hoje)->format('%a');
        $situacao = $diasAtraso >= $this->diasTolerancia ? 'suspenso' : 'tolerancia';
        $resultado = $this->resultado($situacao, true, $assinatura, $cobranca, $diasAtraso, $vencimento->format('Y-m-d'));
        if($situacao === 'suspenso'){ $this->registrarSuspensao($clienteId, $resultado); }
        return $resultado;
    }

    public function clienteEstaRegular(int $clienteId): bool { return $this->avaliar($clienteId)['situacao'] === 'regular'; }
    public function clienteEmTolerancia(int $clienteId): bool { return $this->avaliar($clienteId)['situacao'] === 'tolerancia'; }
    public function clienteSuspenso(int $clienteId): bool { return $this->avaliar($clienteId)['situacao'] === 'suspenso'; }

    private function dataAtual(): \DateTimeImmutable
    {
        $agora = call_user_func($this->agora);
        if(!$agora instanceof \DateTimeInterface){ throw new \LogicException('Relógio financeiro inválido.'); }
        return \DateTimeImmutable::createFromInterface($agora)->setTime(0, 0);
    }

    private function resultado(string $situacao, bool $vinculoAtivo, $assinatura, $cobranca, int $diasAtraso, $vencimento = null): array
    {
        return [
            'situacao'=>$situacao,
            'acesso_operacional'=>$situacao !== 'suspenso',
            'vinculo_ativo'=>$vinculoAtivo,
            'assinatura_id'=>(int) ($assinatura['ASS_ID'] ?? 0) ?: null,
            'cobranca_id'=>(int) ($cobranca['COB_ID'] ?? 0) ?: null,
            'dias_atraso'=>$diasAtraso,
            'vencimento'=>$vencimento,
            'regra'=>$situacao === 'suspenso' ? 'inadimplencia_d_' . $this->diasTolerancia : null
        ];
    }

    private function registrarSuspensao(int $clienteId, array $resultado): void
    {
        $dados = ['data'=>date('c'),'evento'=>'acesso_financeiro_negado','cliente_id'=>$clienteId,'cobranca_id'=>$resultado['cobranca_id'],'vencimento'=>$resultado['vencimento'],'dias_atraso'=>$resultado['dias_atraso'],'regra'=>$resultado['regra']];
        if($this->logger){ call_user_func($this->logger, $dados); return; }
        $dir = function_exists('diretorioLogsProjeto') ? diretorioLogsProjeto() : dirname(__DIR__, 2) . '/storage/logs';
        if(!is_dir($dir)){ mkdir($dir, 0770, true); }
        error_log(json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL, 3, $dir . '/financeiro-acesso.log');
    }
}
