<?php

namespace Services;

class FinanceiroRecorrenciaService
{
    private $diasTolerancia;

    public function __construct($diasTolerancia = null)
    {
        $this->diasTolerancia = (int) ($diasTolerancia ?? (defined('FINANCEIRO_DIAS_TOLERANCIA_VENCIMENTO') ? FINANCEIRO_DIAS_TOLERANCIA_VENCIMENTO : 5));
    }

    public function processarVencimentos()
    {
        return (new FinanceiroWorkflowService(null, null, null, null, null, $this))->processarVencimentos();
    }

    public function gerarCobrancasRecorrentes()
    {
        return (new FinanceiroWorkflowService(null, null, null, null, null, $this))->gerarCobrancasRecorrentes();
    }

    public function diasTolerancia()
    {
        return $this->diasTolerancia;
    }

    public function calcularProximaData($ciclo, $dataBase)
    {
        $meses = ['mensal'=>1, 'trimestral'=>3, 'semestral'=>6, 'anual'=>12][$ciclo] ?? 1;
        return date('Y-m-d', strtotime('+' . $meses . ' months', strtotime($dataBase ?: date('Y-m-d'))));
    }
}
