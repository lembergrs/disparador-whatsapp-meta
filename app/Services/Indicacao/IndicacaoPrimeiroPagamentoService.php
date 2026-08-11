<?php

namespace Services\Indicacao;

use Core\Database;
use Models\Cliente;
use Models\Indicacao;
use Models\IndicacaoCampanha;
use Models\IndicacaoCodigo;
use PDO;

class IndicacaoPrimeiroPagamentoService
{
    private $clientes;
    private $campanhas;
    private $codigosModel;
    private $codigos;
    private $indicacoes;
    private $elegibilidade;

    public function __construct(
        Cliente $clientes = null,
        IndicacaoCampanha $campanhas = null,
        IndicacaoCodigo $codigosModel = null,
        IndicacaoCodigoService $codigos = null,
        Indicacao $indicacoes = null,
        IndicacaoElegibilidadeService $elegibilidade = null,
        PDO $db = null
    ) {
        $db = $db ?: Database::getInstance();
        $this->clientes = $clientes ?: new Cliente($db);
        $this->campanhas = $campanhas ?: new IndicacaoCampanha($db);
        $this->codigosModel = $codigosModel ?: new IndicacaoCodigo($db);
        $this->codigos = $codigos ?: new IndicacaoCodigoService($this->codigosModel, null, null, null, null, $db);
        $this->indicacoes = $indicacoes ?: new Indicacao($db);
        $this->elegibilidade = $elegibilidade ?: IndicacaoElegibilidadeService::padrao();
    }

    public function processar($clienteId, \DateTimeInterface $pagoEm): array
    {
        $clienteId = (int) $clienteId;
        if($clienteId <= 0){
            throw new \InvalidArgumentException('Cliente inválido para o primeiro pagamento.');
        }

        $resultado = ['codigo_id'=>null, 'codigo_ativado'=>false, 'elegibilidade'=>null];
        $campanha = $this->campanhas->buscarPublicaElegivel(true);

        if($campanha){
            $codigo = $this->codigosModel->buscarPorClienteCampanha($clienteId, (int) $campanha['ICP_ID'], true);
            if(!$codigo){
                $cliente = $this->clientes->buscar($clienteId);
                if(!$cliente){
                    throw new \DomainException('Cliente não encontrado para ativação do código de indicação.');
                }
                $codigoId = $this->codigos->criar($clienteId, (int) $campanha['ICP_ID'], $cliente);
                $codigo = $this->codigosModel->buscar($codigoId);
            }

            $resultado['codigo_id'] = (int) $codigo['ICD_ID'];
            if($codigo['ICD_Status'] === 'nao_liberado'){
                $this->codigos->alterarStatus((int) $codigo['ICD_ID'], 'ativo');
                $resultado['codigo_ativado'] = true;
            }
        }

        $indicacao = $this->indicacoes->buscarPorIndicado($clienteId, true);
        if($indicacao && $indicacao['IND_Status'] === 'aguardando_pagamento'){
            $resultado['elegibilidade'] = $this->elegibilidade->confirmarPrimeiroPagamento($clienteId, $pagoEm);
        }

        return $resultado;
    }
}
