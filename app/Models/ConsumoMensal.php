<?php

namespace Models;

use Core\Database;
use PDO;

class ConsumoMensal
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function registrarMensagem($cliId, $plano = null)
    {
        if(!$plano){
            $clienteModel = new Cliente();
            $plano = $clienteModel->buscarComPlano($cliId);
        }

        $plano = is_array($plano) ? $plano : [];
        $planoId = $plano['PLA_ID'] ?? $plano['CMS_PLA_ID'] ?? null;
        $limiteMensagens = $plano['PLA_LimiteMensagens'] ?? $plano['CMS_LimiteMensagens'] ?? null;
        $valorMensagemExcedente = $plano['PLA_ValorMensagemExcedente'] ?? $plano['CMS_ValorMensagemExcedente'] ?? null;
        $planoPago = strtolower((string) ($plano['CLI_StatusPagamento'] ?? '')) === 'pago';

        $anoMes = date('Ym');
        $registro = $this->buscarRegistro($cliId, $anoMes);

        if(!$registro){
            $this->garantirRegistro($cliId, $anoMes, $planoPago, $planoId, $limiteMensagens, $valorMensagemExcedente);
            $registro = $this->buscarRegistro($cliId, $anoMes);
        }

        if($registro){
            $registro = $this->separarAvaliacaoDoPlanoPago($registro, $planoPago, $planoId, $limiteMensagens, $valorMensagemExcedente);

            $camposAtualizacao = [
                'CMS_Mensagens = CMS_Mensagens + 1',
                'CMS_AtualizadoEm = NOW()'
            ];
            $paramsAtualizacao = [];

            if($planoId !== null && $this->colunaExiste('consumo_mensal', 'CMS_PLA_ID')){
                $camposAtualizacao[] = 'CMS_PLA_ID = COALESCE(CMS_PLA_ID, ?)';
                $paramsAtualizacao[] = $planoId;
            }

            if($limiteMensagens !== null && $this->colunaExiste('consumo_mensal', 'CMS_LimiteMensagens')){
                $camposAtualizacao[] = 'CMS_LimiteMensagens = COALESCE(CMS_LimiteMensagens, ?)';
                $paramsAtualizacao[] = (int) $limiteMensagens;
            }

            if($valorMensagemExcedente !== null && $this->colunaExiste('consumo_mensal', 'CMS_ValorMensagemExcedente')){
                $camposAtualizacao[] = 'CMS_ValorMensagemExcedente = COALESCE(CMS_ValorMensagemExcedente, ?)';
                $paramsAtualizacao[] = (float) $valorMensagemExcedente;
            }

            $paramsAtualizacao[] = $registro['CMS_ID'];

            $sql = $this->db->prepare("
                UPDATE consumo_mensal
                SET " . implode(', ', $camposAtualizacao) . "
                WHERE CMS_ID = ?
            ");

            return $sql->execute($paramsAtualizacao);
        }

        return false;
    }

    public function buscarMesAtual($cliId)
    {
        $anoMes = date('Ym');
        $registro = $this->buscarRegistro($cliId, $anoMes);

        if(!$registro){
            return false;
        }

        if($this->colunaExiste('consumo_mensal', 'CMS_InicioPlanoPago') && empty($registro['CMS_InicioPlanoPago'])){
            $clienteModel = new Cliente();
            $plano = $clienteModel->buscarComPlano($cliId);
            $plano = is_array($plano) ? $plano : [];
            $planoPago = strtolower((string) ($plano['CLI_StatusPagamento'] ?? '')) === 'pago';

            if($planoPago){
                $this->separarAvaliacaoDoPlanoPago(
                    $registro,
                    true,
                    $plano['PLA_ID'] ?? null,
                    $plano['PLA_LimiteMensagens'] ?? null,
                    $plano['PLA_ValorMensagemExcedente'] ?? null
                );
                $registro = $this->buscarRegistro($cliId, $anoMes);
            }
        }

        return $registro;
    }

    private function garantirRegistro($cliId, $anoMes, $planoPago, $planoId, $limiteMensagens, $valorMensagemExcedente)
    {
        $campos = ['CLI_ID', 'CMS_AnoMes', 'CMS_Mensagens'];
        $placeholders = ['?', '?', '0'];
        $params = [$cliId, $anoMes];

        if($planoId !== null && $this->colunaExiste('consumo_mensal', 'CMS_PLA_ID')){
            $campos[] = 'CMS_PLA_ID';
            $placeholders[] = '?';
            $params[] = $planoId;
        }

        if($limiteMensagens !== null && $this->colunaExiste('consumo_mensal', 'CMS_LimiteMensagens')){
            $campos[] = 'CMS_LimiteMensagens';
            $placeholders[] = '?';
            $params[] = (int) $limiteMensagens;
        }

        if($valorMensagemExcedente !== null && $this->colunaExiste('consumo_mensal', 'CMS_ValorMensagemExcedente')){
            $campos[] = 'CMS_ValorMensagemExcedente';
            $placeholders[] = '?';
            $params[] = (float) $valorMensagemExcedente;
        }

        if($this->colunaExiste('consumo_mensal', 'CMS_MensagensAvaliacao')){
            $campos[] = 'CMS_MensagensAvaliacao';
            $placeholders[] = '0';
        }

        if($planoPago && $this->colunaExiste('consumo_mensal', 'CMS_InicioPlanoPago')){
            $campos[] = 'CMS_InicioPlanoPago';
            $placeholders[] = 'NOW()';
        }

        $sql = $this->db->prepare("
            INSERT IGNORE INTO consumo_mensal
            (" . implode(', ', $campos) . ")
            VALUES
            (" . implode(', ', $placeholders) . ")
        ");

        return $sql->execute($params);
    }

    private function separarAvaliacaoDoPlanoPago(array $registro, $planoPago, $planoId, $limiteMensagens, $valorMensagemExcedente)
    {
        if(!$planoPago || !$this->colunaExiste('consumo_mensal', 'CMS_InicioPlanoPago') || !empty($registro['CMS_InicioPlanoPago'])){
            return $registro;
        }

        $campos = [
            'CMS_MensagensAvaliacao = CMS_Mensagens',
            'CMS_Mensagens = 0',
            'CMS_InicioPlanoPago = NOW()',
            'CMS_AtualizadoEm = NOW()'
        ];
        $params = [];

        if($planoId !== null && $this->colunaExiste('consumo_mensal', 'CMS_PLA_ID')){
            $campos[] = 'CMS_PLA_ID = ?';
            $params[] = $planoId;
        }
        if($limiteMensagens !== null && $this->colunaExiste('consumo_mensal', 'CMS_LimiteMensagens')){
            $campos[] = 'CMS_LimiteMensagens = ?';
            $params[] = (int) $limiteMensagens;
        }
        if($valorMensagemExcedente !== null && $this->colunaExiste('consumo_mensal', 'CMS_ValorMensagemExcedente')){
            $campos[] = 'CMS_ValorMensagemExcedente = ?';
            $params[] = (float) $valorMensagemExcedente;
        }

        $params[] = $registro['CMS_ID'];
        $sql = $this->db->prepare("
            UPDATE consumo_mensal
            SET " . implode(', ', $campos) . "
            WHERE CMS_ID = ?
            AND CMS_InicioPlanoPago IS NULL
        ");
        $sql->execute($params);

        return $this->buscarRegistro($registro['CLI_ID'], $registro['CMS_AnoMes']);
    }

    private function buscarRegistro($cliId, $anoMes)
    {
        $sql = $this->db->prepare("
            SELECT *
            FROM consumo_mensal
            WHERE CLI_ID = ?
            AND CMS_AnoMes = ?
        ");
        $sql->execute([$cliId, $anoMes]);
        return $sql->fetch(PDO::FETCH_ASSOC);
    }

    private function colunaExiste($tabela, $coluna)
    {
        static $cache = [];
        $chave = $tabela . '.' . $coluna;

        if(array_key_exists($chave, $cache)){
            return $cache[$chave];
        }

        $sql = $this->db->prepare("SHOW COLUMNS FROM {$tabela} LIKE ?");
        $sql->execute([$coluna]);
        $cache[$chave] = (bool) $sql->fetch(PDO::FETCH_ASSOC);

        return $cache[$chave];
    }
}
