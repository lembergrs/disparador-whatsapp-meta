<?php

namespace Services\Indicacao;

use Models\Cliente;
use Models\Indicacao;
use Models\IndicacaoCampanha;
use Services\IndicacaoCodigoService;
use Services\IndicacaoService;
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

    public function __construct(PDO $db, IndicacaoCodigoService $codigos, IndicacaoCampanha $campanhas, Indicacao $indicacoes, Cliente $clientes, IndicacaoService $indicacaoService)
    {
        $this->db = $db;
        $this->codigos = $codigos;
        $this->campanhas = $campanhas;
        $this->indicacoes = $indicacoes;
        $this->clientes = $clientes;
        $this->indicacaoService = $indicacaoService;
    }

    public function validarCodigo($codigo, $lock = false): array
    {
        $normalizado = CodigoIndicacaoNormalizer::normalizar($codigo);
        if($normalizado === '') $this->codigoInvalido();

        $registro = $this->codigos->buscar($normalizado, $lock);
        if(!$registro || $registro['ICD_Status'] !== 'ativo') $this->codigoInvalido();

        $campanha = $this->campanhas->buscar($registro['ICP_ID'], $lock);
        if(!$this->campanhaElegivel($campanha)) $this->codigoInvalido();
        if((int)$registro['ICP_ID'] !== (int)$campanha['ICP_ID']) $this->codigoInvalido();

        $indicador = $this->clientes->buscar($registro['CLI_ID']);
        if(!$indicador || (int)$registro['CLI_ID'] !== (int)$indicador['CLI_ID']) $this->codigoInvalido();

        return ['codigo'=>$registro, 'campanha'=>$campanha, 'indicador'=>$indicador];
    }

    public function registrarIndicacao($clienteIndicadoId, $codigo, $origem): int
    {
        if(!in_array($origem, ['link','manual'], true)) throw new \InvalidArgumentException('Origem da indicação inválida.');
        $clienteIndicadoId = (int)$clienteIndicadoId;
        if($clienteIndicadoId <= 0) throw new \DomainException('Cliente indicado inválido.');

        return $this->transacao(function() use ($clienteIndicadoId, $codigo, $origem){
            $validacao = $this->validarCodigo($codigo, true);
            $indicado = $this->clientes->buscar($clienteIndicadoId);
            if(!$indicado) throw new \DomainException('Cliente indicado inválido.');

            $indicadorId = (int)$validacao['codigo']['CLI_ID'];
            if($indicadorId === $clienteIndicadoId) throw new \DomainException('Autoindicação não permitida.');
            if($this->indicacoes->buscarPorIndicado($clienteIndicadoId, true)) throw new \DomainException('Cliente indicado já possui uma indicação.');

            try{
                $id = $this->indicacaoService->criar([
                    'codigo_id'=>(int)$validacao['codigo']['ICD_ID'],
                    'campanha_id'=>(int)$validacao['campanha']['ICP_ID'],
                    'indicador_id'=>$indicadorId,
                    'indicado_id'=>$clienteIndicadoId,
                    'origem'=>$origem,
                ]);
                $this->indicacaoService->transicionar($id, 'aguardando_pagamento');
                return $id;
            }catch(PDOException $e){
                if($this->violacaoUnica($e)) throw new \DomainException('Cliente indicado já possui uma indicação.', 0, $e);
                throw $e;
            }
        });
    }

    public function cancelarIndicacao($indicacaoId, $motivo): bool
    {
        $motivo = trim((string)$motivo);
        if($motivo === '') throw new \InvalidArgumentException('Motivo do cancelamento é obrigatório.');
        return $this->transacao(function() use ($indicacaoId, $motivo){
            return $this->indicacaoService->transicionar((int)$indicacaoId, 'cancelada', $motivo);
        });
    }

    private function campanhaElegivel($campanha): bool
    {
        if(!$campanha || $campanha['ICP_Ativo'] !== 'S' || $campanha['ICP_Publica'] !== 'S') return false;
        $agora = new \DateTimeImmutable('now');
        $inicio = new \DateTimeImmutable($campanha['ICP_DataInicio']);
        if($inicio > $agora) return false;
        if(!empty($campanha['ICP_DataFim']) && new \DateTimeImmutable($campanha['ICP_DataFim']) < $agora) return false;
        return true;
    }

    private function codigoInvalido(): void
    {
        throw new \DomainException('Código de indicação inválido ou indisponível.');
    }

    private function violacaoUnica(PDOException $e): bool
    {
        return in_array((string)$e->getCode(), ['23000','19'], true) || strpos(strtolower($e->getMessage()), 'unique') !== false;
    }

    private function transacao(callable $callback)
    {
        $propria = !$this->db->inTransaction();
        if($propria) $this->db->beginTransaction();
        try{
            $resultado = $callback();
            if($propria) $this->db->commit();
            return $resultado;
        }catch(\Throwable $e){
            if($propria && $this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }
}
