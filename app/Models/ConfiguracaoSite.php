<?php

namespace Models;

use Core\Database;
use PDO;

class ConfiguracaoSite
{
    public const MENSAGEM_PADRAO = 'Olá! Gostaria de conhecer melhor o Disparador.net.';

    private $db;

    public function __construct($db = null)
    {
        $this->db = $db ?: Database::getInstance();
    }

    public function buscar()
    {
        $sql = $this->db->query("
            SELECT CWS_Ativo, MTA_ID, CWS_Mensagem
            FROM configuracao_whatsapp_site
            WHERE CWS_ID = 1
            LIMIT 1
        ");

        return $sql->fetch(PDO::FETCH_ASSOC) ?: [
            'CWS_Ativo' => 'N',
            'MTA_ID' => null,
            'CWS_Mensagem' => self::MENSAGEM_PADRAO
        ];
    }

    public function contasElegiveis()
    {
        return $this->db->query("
            SELECT m.MTA_ID, m.MTA_Nome, m.MTA_NumeroTelefone, c.CLI_Nome
            FROM meta_contas m
            INNER JOIN clientes c ON c.CLI_ID = m.CLI_ID
            WHERE m.MTA_Ativo = 'S'
            AND m.MTA_Status = 'conectado'
            AND m.MTA_NumeroTelefone IS NOT NULL
            AND m.MTA_NumeroTelefone <> ''
            ORDER BY c.CLI_Nome, m.MTA_Nome, m.MTA_ID
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function contaElegivel($metaContaId)
    {
        $sql = $this->db->prepare("
            SELECT MTA_ID, MTA_NumeroTelefone
            FROM meta_contas
            WHERE MTA_ID = ?
            AND MTA_Ativo = 'S'
            AND MTA_Status = 'conectado'
            LIMIT 1
        ");
        $sql->execute([(int) $metaContaId]);

        return $sql->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function normalizarTelefone($telefone)
    {
        $telefone = preg_replace('/\D+/', '', (string) $telefone);

        return preg_match('/^[0-9]{10,15}$/', $telefone) ? $telefone : null;
    }

    public function obterConfiguracaoWhatsappSite()
    {
        try{
            $configuracao = $this->buscar();
            if(($configuracao['CWS_Ativo'] ?? 'N') !== 'S'){
                return null;
            }

            $mensagem = trim((string) ($configuracao['CWS_Mensagem'] ?? ''));
            $conta = $this->contaElegivel($configuracao['MTA_ID'] ?? 0);
            $telefone = self::normalizarTelefone($conta['MTA_NumeroTelefone'] ?? '');

            if(!$conta || !$telefone || $mensagem === '' || mb_strlen($mensagem) > 500){
                error_log('Configuração do botão WhatsApp público está ativa, mas incompleta ou inelegível.');
                return null;
            }

            return ['ativo' => true, 'telefone' => $telefone, 'mensagem' => $mensagem];
        }catch(\Throwable $e){
            error_log('Botão WhatsApp público indisponível: ' . $e->getMessage());
            return null;
        }
    }

    public function salvar($ativo, $metaContaId, $mensagem)
    {
        $sql = $this->db->prepare("
            INSERT INTO configuracao_whatsapp_site (CWS_ID, CWS_Ativo, MTA_ID, CWS_Mensagem)
            VALUES (1, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                CWS_Ativo = VALUES(CWS_Ativo),
                MTA_ID = VALUES(MTA_ID),
                CWS_Mensagem = VALUES(CWS_Mensagem),
                CWS_AtualizadoEm = CURRENT_TIMESTAMP
        ");

        return $sql->execute([$ativo, $metaContaId ?: null, $mensagem]);
    }
}
