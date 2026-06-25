<?php

namespace Models;

use Core\Database;
use PDO;

class Cobranca
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function buscarPendentePorCliente($clienteId)
    {
        $sql = $this->db->prepare("
            SELECT c.*, p.PLA_Nome
            FROM cobrancas c
            LEFT JOIN planos p ON p.PLA_ID = c.PLA_ID
            WHERE c.CLI_ID = ?
            AND c.COB_Status = 'pendente'
            ORDER BY c.COB_ID DESC
            LIMIT 1
        ");

        $sql->execute([$clienteId]);

        return $sql->fetch(PDO::FETCH_ASSOC);
    }

    public function criar($dados)
    {
        $campos = [
            'CLI_ID',
            'PLA_ID',
            'COB_Valor',
            'COB_Status',
            'COB_Forma',
            'COB_DataVencimento'
        ];
        $valores = [
            ':cliente',
            ':plano',
            ':valor',
            "'pendente'",
            "'bolepix'",
            ':vencimento'
        ];
        $params = [
            ':cliente' => $dados['cliente'],
            ':plano' => $dados['plano'],
            ':valor' => $dados['valor'],
            ':vencimento' => $dados['vencimento']
        ];

        if($this->colunaExiste('cobrancas', 'COB_Tipo')){
            $campos[] = 'COB_Tipo';
            $valores[] = ':tipo';
            $params[':tipo'] = $dados['tipo'] ?? 'mensalidade';
        }

        $camposOpcionais = [
            'ASS_ID' => 'assinatura',
            'COB_Provider' => 'provider',
            'COB_ProviderCustomerId' => 'provider_customer_id',
            'COB_ProviderPaymentId' => 'provider_payment_id',
            'COB_ProviderStatus' => 'provider_status',
            'COB_ProviderPayload' => 'provider_payload',
            'COB_LinkPagamento' => 'link_pagamento',
            'COB_PixCopiaCola' => 'pix_copia_cola',
            'COB_QrCode' => 'qr_code',
            'COB_LinhaDigitavel' => 'linha_digitavel'
        ];

        foreach($camposOpcionais as $coluna => $chave){
            if($this->colunaExiste('cobrancas', $coluna) && array_key_exists($chave, $dados)){
                $param = ':' . $chave;
                $campos[] = $coluna;
                $valores[] = $param;
                $params[$param] = $dados[$chave];
            }
        }

        if($this->colunaExiste('cobrancas', 'COB_DataSincronizacaoProvider') && !empty($dados['sincronizado_provider'])){
            $campos[] = 'COB_DataSincronizacaoProvider';
            $valores[] = 'NOW()';
        }

        $sql = $this->db->prepare("
            INSERT INTO cobrancas (
                " . implode(', ', $campos) . "
            ) VALUES (
                " . implode(', ', $valores) . "
            )
        ");

        $sql->execute($params);

        return $this->db->lastInsertId();
    }

    public function marcarPago($id)
    {
        $sql = $this->db->prepare("
            UPDATE cobrancas
            SET
                COB_Status = 'pago',
                COB_DataPagamento = NOW()
            WHERE COB_ID = ?
            AND COB_Status IN ('pendente','vencido')
        ");

        return $sql->execute([$id]);
    }

    public function listar()
    {
        $sql = $this->db->query("
            SELECT
                c.*,
                cli.CLI_Nome,
                p.PLA_Nome
            FROM cobrancas c
            LEFT JOIN clientes cli
                ON cli.CLI_ID = c.CLI_ID
            LEFT JOIN planos p
                ON p.PLA_ID = c.PLA_ID
            ORDER BY c.COB_ID DESC
        ");

        return $sql->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buscar($id)
    {
        $sql = $this->db->prepare("
            SELECT *
            FROM cobrancas
            WHERE COB_ID = ?
        ");

        $sql->execute([$id]);

        return $sql->fetch(PDO::FETCH_ASSOC);
    }

    public function cancelar($id)
    {
        $sql = $this->db->prepare("
            UPDATE cobrancas
            SET COB_Status = 'cancelado'
            WHERE COB_ID = ?
        ");

        return $sql->execute([$id]);
    }

    public function atualizarIntegracaoProvider($id, $dados)
    {
        $sets = [];
        $params = [':id' => $id];
        $mapa = [
            'COB_Provider' => 'provider',
            'COB_ProviderCustomerId' => 'provider_customer_id',
            'COB_ProviderPaymentId' => 'provider_payment_id',
            'COB_ProviderStatus' => 'provider_status',
            'COB_ProviderPayload' => 'provider_payload',
            'COB_LinkPagamento' => 'link_pagamento',
            'COB_PixCopiaCola' => 'pix_copia_cola',
            'COB_QrCode' => 'qr_code',
            'COB_LinhaDigitavel' => 'linha_digitavel',
            'COB_Status' => 'status',
            'COB_DataPagamento' => 'data_pagamento'
        ];

        foreach($mapa as $coluna => $chave){
            if($this->colunaExiste('cobrancas', $coluna) && array_key_exists($chave, $dados)){
                $param = ':' . $chave;
                $sets[] = $coluna . ' = ' . $param;
                $params[$param] = $dados[$chave];
            }
        }

        if($this->colunaExiste('cobrancas', 'COB_DataSincronizacaoProvider')){
            $sets[] = 'COB_DataSincronizacaoProvider = NOW()';
        }

        if(empty($sets)){
            return false;
        }

        $sql = $this->db->prepare("\n            UPDATE cobrancas\n            SET " . implode(', ', $sets) . "\n            WHERE COB_ID = :id\n        ");

        return $sql->execute($params);
    }

    public function buscarPorProviderPaymentId($provider, $providerPaymentId)
    {
        if(!$this->colunaExiste('cobrancas', 'COB_Provider') || !$this->colunaExiste('cobrancas', 'COB_ProviderPaymentId')){
            return false;
        }

        $sql = $this->db->prepare("\n            SELECT *\n            FROM cobrancas\n            WHERE COB_Provider = ?\n            AND COB_ProviderPaymentId = ?\n            ORDER BY COB_ID DESC\n            LIMIT 1\n        ");

        $sql->execute([$provider, $providerPaymentId]);

        return $sql->fetch(PDO::FETCH_ASSOC);
    }

    public function registrarEventoProvider($cobrancaId, $provider, $providerEventId, $evento, $status, $payload)
    {
        if(!$this->tabelaExiste('cobranca_eventos')){
            return false;
        }

        if($providerEventId !== '' && $this->eventoProviderExiste($provider, $providerEventId)){
            return 'duplicado';
        }

        $campos = [];
        $valores = [];
        $params = [];
        $mapa = [
            'COB_ID' => $cobrancaId,
            'CEV_Provider' => $provider,
            'CEV_ProviderEventId' => $providerEventId,
            'CEV_Evento' => $evento,
            'CEV_Status' => $status,
            'CEV_Payload' => $payload
        ];

        foreach($mapa as $coluna => $valor){
            if($this->colunaExiste('cobranca_eventos', $coluna)){
                $param = ':' . strtolower($coluna);
                $campos[] = $coluna;
                $valores[] = $param;
                $params[$param] = $valor;
            }
        }

        foreach(['CEV_DataCadastro', 'CEV_DataRecebimento', 'CEV_DataEvento'] as $colunaData){
            if($this->colunaExiste('cobranca_eventos', $colunaData)){
                $campos[] = $colunaData;
                $valores[] = 'NOW()';
                break;
            }
        }

        if(empty($campos)){
            return false;
        }

        $sql = $this->db->prepare("\n            INSERT INTO cobranca_eventos (" . implode(', ', $campos) . ")\n            VALUES (" . implode(', ', $valores) . ")\n        ");

        return $sql->execute($params);
    }

    private function eventoProviderExiste($provider, $providerEventId)
    {
        if(!$this->colunaExiste('cobranca_eventos', 'CEV_Provider') || !$this->colunaExiste('cobranca_eventos', 'CEV_ProviderEventId')){
            return false;
        }

        $sql = $this->db->prepare("\n            SELECT 1\n            FROM cobranca_eventos\n            WHERE CEV_Provider = ?\n            AND CEV_ProviderEventId = ?\n            LIMIT 1\n        ");

        $sql->execute([$provider, $providerEventId]);

        return (bool) $sql->fetchColumn();
    }

    private function tabelaExiste($tabela)
    {
        static $cache = [];

        if(array_key_exists($tabela, $cache)){
            return $cache[$tabela];
        }

        $sql = $this->db->prepare("\n            SHOW TABLES LIKE ?\n        ");

        $sql->execute([$tabela]);

        $cache[$tabela] = (bool) $sql->fetch(PDO::FETCH_ASSOC);

        return $cache[$tabela];
    }



    public function vincularAssinatura($cobrancaId, $assinaturaId)
    {
        if(!$this->colunaExiste('cobrancas', 'ASS_ID')){
            return false;
        }

        $sql = $this->db->prepare("\n            UPDATE cobrancas\n            SET ASS_ID = ?\n            WHERE COB_ID = ?\n        ");

        return $sql->execute([$assinaturaId, $cobrancaId]);
    }

    public function buscarPorProviderPaymentId($provider, $providerPaymentId)
    {
        if(!$this->colunaExiste('cobrancas', 'COB_Provider') || !$this->colunaExiste('cobrancas', 'COB_ProviderPaymentId')){
            return false;
        }

        $sql = $this->db->prepare("\n            SELECT *\n            FROM cobrancas\n            WHERE COB_Provider = ?\n            AND COB_ProviderPaymentId = ?\n            ORDER BY COB_ID DESC\n            LIMIT 1\n        ");

        $sql->execute([$provider, $providerPaymentId]);

        return $sql->fetch(PDO::FETCH_ASSOC);
    }

    public function registrarEventoProvider($cobrancaId, $provider, $providerEventId, $evento, $status, $payload)
    {
        if(!$this->tabelaExiste('cobranca_eventos')){
            return false;
        }

        if($providerEventId !== '' && $this->eventoProviderExiste($provider, $providerEventId)){
            return 'duplicado';
        }

        $campos = [];
        $valores = [];
        $params = [];
        $mapa = [
            'COB_ID' => $cobrancaId,
            'CEV_Provider' => $provider,
            'CEV_ProviderEventId' => $providerEventId,
            'CEV_Evento' => $evento,
            'CEV_Status' => $status,
            'CEV_Payload' => $payload
        ];

        foreach($mapa as $coluna => $valor){
            if($this->colunaExiste('cobranca_eventos', $coluna)){
                $param = ':' . strtolower($coluna);
                $campos[] = $coluna;
                $valores[] = $param;
                $params[$param] = $valor;
            }
        }

        foreach(['CEV_DataCadastro', 'CEV_DataRecebimento', 'CEV_DataEvento'] as $colunaData){
            if($this->colunaExiste('cobranca_eventos', $colunaData)){
                $campos[] = $colunaData;
                $valores[] = 'NOW()';
                break;
            }
        }

        if(empty($campos)){
            return false;
        }

        $sql = $this->db->prepare("\n            INSERT INTO cobranca_eventos (" . implode(', ', $campos) . ")\n            VALUES (" . implode(', ', $valores) . ")\n        ");

        return $sql->execute($params);
    }

    private function eventoProviderExiste($provider, $providerEventId)
    {
        if(!$this->colunaExiste('cobranca_eventos', 'CEV_Provider') || !$this->colunaExiste('cobranca_eventos', 'CEV_ProviderEventId')){
            return false;
        }

        $sql = $this->db->prepare("\n            SELECT 1\n            FROM cobranca_eventos\n            WHERE CEV_Provider = ?\n            AND CEV_ProviderEventId = ?\n            LIMIT 1\n        ");

        $sql->execute([$provider, $providerEventId]);

        return (bool) $sql->fetchColumn();
    }

    private function tabelaExiste($tabela)
    {
        static $cache = [];

        if(array_key_exists($tabela, $cache)){
            return $cache[$tabela];
        }

        $sql = $this->db->prepare("\n            SHOW TABLES LIKE ?\n        ");

        $sql->execute([$tabela]);

        $cache[$tabela] = (bool) $sql->fetch(PDO::FETCH_ASSOC);

        return $cache[$tabela];
    }




    private function colunaExiste($tabela, $coluna)
    {
        static $cache = [];

        $chave = $tabela . '.' . $coluna;

        if(array_key_exists($chave, $cache)){
            return $cache[$chave];
        }

        $sql = $this->db->prepare("
            SHOW COLUMNS FROM {$tabela} LIKE ?
        ");

        $sql->execute([$coluna]);

        $cache[$chave] = (bool) $sql->fetch(PDO::FETCH_ASSOC);

        return $cache[$chave];
    }
}
