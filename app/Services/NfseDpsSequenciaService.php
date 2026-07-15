<?php

namespace Services;

use Core\Database;
use Models\NfseEmissao;
use PDO;

class NfseDpsSequenciaService
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function reservar($prestadorCnpj = null, $ambiente = null, $serie = null)
    {
        $prestadorCnpj = preg_replace('/\D/', '', (string) ($prestadorCnpj ?? NfseConfigService::prestadorCnpj()));
        $ambiente = trim((string) ($ambiente ?? NfseConfigService::ambiente()));
        $serie = trim((string) ($serie ?? NfseConfigService::dpsSerie()));

        if($prestadorCnpj === '' || $ambiente === '' || $serie === ''){
            throw new \InvalidArgumentException('Contexto fiscal incompleto para reservar numDPS.');
        }

        $this->db->beginTransaction();

        try{
            $this->garantirSequencia($prestadorCnpj, $ambiente, $serie);

            $select = $this->db->prepare("
                SELECT NDS_ProximoNumero
                FROM nfse_dps_sequencias
                WHERE NDS_PrestadorCnpj = ?
                AND NDS_Ambiente = ?
                AND NDS_Serie = ?
                FOR UPDATE
            ");
            $select->execute([$prestadorCnpj, $ambiente, $serie]);
            $numero = (int) $select->fetchColumn();

            $update = $this->db->prepare("
                UPDATE nfse_dps_sequencias
                SET NDS_ProximoNumero = NDS_ProximoNumero + 1,
                    NDS_DataAtualizacao = NOW()
                WHERE NDS_PrestadorCnpj = ?
                AND NDS_Ambiente = ?
                AND NDS_Serie = ?
            ");
            $update->execute([$prestadorCnpj, $ambiente, $serie]);

            $this->db->commit();

            return (string) $numero;
        }catch(\Throwable $e){
            $this->db->rollBack();
            throw $e;
        }
    }

    public function reservarParaEmissao($nfseId, $prestadorCnpj = null, $ambiente = null, $serie = null)
    {
        $emissaoModel = new NfseEmissao();
        $numDps = $this->reservar($prestadorCnpj, $ambiente, $serie);
        $emissaoModel->atribuirNumDps($nfseId, $numDps);

        return $numDps;
    }

    private function garantirSequencia($prestadorCnpj, $ambiente, $serie)
    {
        $insert = $this->db->prepare("
            INSERT INTO nfse_dps_sequencias (NDS_PrestadorCnpj, NDS_Ambiente, NDS_Serie, NDS_ProximoNumero)
            VALUES (?, ?, ?, 1)
            ON DUPLICATE KEY UPDATE NDS_PrestadorCnpj = VALUES(NDS_PrestadorCnpj)
        ");
        $insert->execute([$prestadorCnpj, $ambiente, $serie]);
    }
}
