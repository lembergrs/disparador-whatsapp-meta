<?php

namespace Services\Indicacao;

use Core\Database;
use Models\Cliente;
use Models\Indicacao;
use Models\IndicacaoAuditoria;
use Models\IndicacaoCampanha;
use Models\IndicacaoCodigo;
use PDO;
use PDOException;

class IndicacaoWorkflowService
{
    private $db;
    private $codigos;
    private $campanhas;
    private $indicacoes;
    private $clientes;
    private $indicacaoService;

    public function __construct(
        PDO $db = null,
        IndicacaoCodigoService $codigos = null,
        IndicacaoCampanha $campanhas = null,
        Indicacao $indicacoes = null,
        Cliente $clientes = null,
        IndicacaoService $indicacaoService = null
    ) {
        $this->db = $db ?: Database::getInstance();
        $this->campanhas = $campanhas ?: new IndicacaoCampanha($this->db);
        $this->indicacoes = $indicacoes ?: new Indicacao($this->db);
        $this->clientes = $clientes ?: new Cliente($this->db);

        $codigoModel = new IndicacaoCodigo($this->db);
        $audit = new IndicacaoAuditoriaService(new IndicacaoAuditoria($this->db));
        $this->codigos = $codigos ?: new IndicacaoCodigoService($codigoModel, null, null, $audit, null, $this->db);
        $this->indicacaoService = $indicacaoService ?: new IndicacaoService(
            $this->indicacoes,
            $this->campanhas,
            $codigoModel,
            $audit,
            null,
            $this->db
        );
    }

    public function validarCodigo($codigo, $forUpdate = false): array
    {
        $normalizado = CodigoIndicacaoNormalizer::normalizar($codigo);
        if($normalizado === ''){
            $this->codigoInvalido();
        }

        $registro = $this->codigos->buscar($normalizado, $forUpdate);
        if(!$registro || $registro['ICD_Status'] !== 'ativo'){
            $this->codigoInvalido();
        }

        $campanha = $this->campanhas->buscar($registro['ICP_ID'], $forUpdate);
        if(!$this->campanhaElegivel($campanha)){
            $this->codigoInvalido();
        }

        if((int)$registro['ICP_ID'] !== (int)$campanha['ICP_ID']){
            $this->codigoInvalido();
        }

        $indicador = $this->clientes->buscar($registro['CLI_ID']);
        if(!$indicador || (int)$registro['CLI_ID'] !== (int)$indicador['CLI_ID']){
            $this->codigoInvalido();
        }

        return [
            'codigo' => $registro,
            'campanha' => $campanha,
            'indicador' => $indicador
        ];
    }

    public function registrarIndicacao($clienteIndicadoId, $codigo, $origem): int
    {
        if(!in_array($origem, ['link','manual'], true)){
            throw new \InvalidArgumentException('Origem da indicação inválida.');
        }

        $clienteIndicadoId = (int)$clienteIndicadoId;
        if($clienteIndicadoId <= 0){
            throw new \DomainException('Cliente indicado inválido.');
        }

        return $this->transacao(function() use ($clienteIndicadoId, $codigo, $origem){
            $validacao = $this->validarCodigo($codigo, true);
            $indicado = $this->clientes->buscar($clienteIndicadoId);
            if(!$indicado){
                throw new \DomainException('Cliente indicado inválido.');
            }

            $indicadorId = (int)$validacao['codigo']['CLI_ID'];
            if($indicadorId === $clienteIndicadoId){
                throw new \DomainException('Autoindicação não permitida.');
            }

            if($this->indicacoes->buscarPorIndicado($clienteIndicadoId, true)){
                throw new \DomainException('Cliente indicado já possui uma indicação.');
            }

            try{
                $id = $this->indicacaoService->criar(
                    (int)$validacao['codigo']['ICD_ID'],
                    $indicadorId,
                    $clienteIndicadoId,
                    $origem
                );
                $this->indicacaoService->alterarStatus($id, 'aguardando_pagamento');
                return $id;
            }catch(PDOException $e){
                if($this->violacaoUnica($e)){
                    throw new \DomainException('Cliente indicado já possui uma indicação.', 0, $e);
                }
                throw $e;
            }
        });
    }

    public function cancelarIndicacao($indicacaoId, $motivo): bool
    {
        $motivo = trim((string)$motivo);
        if($motivo === ''){
            throw new \InvalidArgumentException('Motivo do cancelamento é obrigatório.');
        }

        return $this->transacao(function() use ($indicacaoId, $motivo){
            $this->indicacaoService->alterarStatus((int)$indicacaoId, 'cancelada', null, $motivo, [
                'cancelada_em' => date('Y-m-d H:i:s')
            ]);
            return true;
        });
    }

    private function campanhaElegivel($campanha): bool
    {
        if(!$campanha || $campanha['ICP_Ativo'] !== 'S' || $campanha['ICP_Publica'] !== 'S'){
            return false;
        }

        $agora = new \DateTimeImmutable('now');
        if(!empty($campanha['ICP_DataInicio'])){
            $inicio = new \DateTimeImmutable($campanha['ICP_DataInicio']);
            if($inicio > $agora){
                return false;
            }
        }

        if(!empty($campanha['ICP_DataFim'])){
            $fim = new \DateTimeImmutable($campanha['ICP_DataFim']);
            if($fim < $agora){
                return false;
            }
        }

        return true;
    }

    private function codigoInvalido(): void
    {
        throw new \DomainException('Código de indicação inválido ou indisponível.');
    }

    private function violacaoUnica(PDOException $e): bool
    {
        if(in_array((string)$e->getCode(), ['23000','19'], true)){
            return true;
        }

        $mensagem = strtolower($e->getMessage());
        return strpos($mensagem, 'unique') !== false || strpos($mensagem, 'duplicate') !== false;
    }

    private function transacao(callable $callback)
    {
        $propria = !$this->db->inTransaction();
        if($propria){
            $this->db->beginTransaction();
        }

        try{
            $resultado = $callback();
            if($propria){
                $this->db->commit();
            }
            return $resultado;
        }catch(\Throwable $e){
            if($propria && $this->db->inTransaction()){
                $this->db->rollBack();
            }
            throw $e;
        }
    }
}
