<?php

namespace Services;

class FinanceiroRecorrenciaService
{
    private $diasTolerancia;
    private $diasAntecedencia;
    private $diasVencimentoRecuperacao;

    public function __construct($diasTolerancia = null, $diasAntecedencia = null, $diasVencimentoRecuperacao = null)
    {
        $this->diasTolerancia = (int) ($diasTolerancia ?? (defined('FINANCEIRO_DIAS_TOLERANCIA_VENCIMENTO') ? FINANCEIRO_DIAS_TOLERANCIA_VENCIMENTO : 5));
        $this->diasAntecedencia = max(0, (int) ($diasAntecedencia ?? (defined('FINANCEIRO_DIAS_ANTECEDENCIA_COBRANCA') ? FINANCEIRO_DIAS_ANTECEDENCIA_COBRANCA : 7)));
        $this->diasVencimentoRecuperacao = max(1, (int) ($diasVencimentoRecuperacao ?? (defined('FINANCEIRO_DIAS_VENCIMENTO_RECUPERACAO') ? FINANCEIRO_DIAS_VENCIMENTO_RECUPERACAO : 3)));
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

    public function diasAntecedencia()
    {
        return $this->diasAntecedencia;
    }

    public function vencimentoEfetivoGateway($competencia, $hoje = null)
    {
        $hoje = $hoje ?: date('Y-m-d');
        if((string) $competencia >= (string) $hoje){
            return $competencia;
        }
        return date('Y-m-d', strtotime('+' . $this->diasVencimentoRecuperacao . ' days', strtotime($hoje)));
    }

    public function calcularProximaData($ciclo, $dataBase)
    {
        $meses = ['mensal'=>1, 'trimestral'=>3, 'semestral'=>6, 'anual'=>12][$ciclo] ?? 1;
        return date('Y-m-d', strtotime('+' . $meses . ' months', strtotime($dataBase ?: date('Y-m-d'))));
    }
}
