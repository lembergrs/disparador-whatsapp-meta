<?php

namespace Models;

use Core\Database;
use PDO;

class OnboardingSuporteSolicitacao
{
    public const ASSUNTOS = [
        'nao_consigo_avancar' => 'Não consigo avançar nesta etapa',
        'duvida_configuracao' => 'Tenho uma dúvida sobre a configuração',
        'mensagem_erro' => 'Recebi uma mensagem de erro',
        'orientacao' => 'Preciso de orientação',
        'outro' => 'Outro'
    ];

    public const PERIODOS = [
        'manha' => 'Manhã',
        'tarde' => 'Tarde',
        'noite' => 'Noite',
        'qualquer' => 'Qualquer horário'
    ];

    public const STATUS = ['aberta','em_atendimento','concluida','cancelada'];

    private $db;

    public function __construct($db = null)
    {
        $this->db = $db ?: Database::getInstance();
    }

    public function contaPertenceAoCliente($clienteId, $contaId)
    {
        if((int) $contaId <= 0){
            return false;
        }

        $sql = $this->db->prepare("
            SELECT 1
            FROM meta_contas
            WHERE CLI_ID = ?
            AND MTA_ID = ?
            AND MTA_Ativo = 'S'
            LIMIT 1
        ");
        $sql->execute([(int) $clienteId, (int) $contaId]);

        return (bool) $sql->fetchColumn();
    }

    public function criar(array $dados)
    {
        $clienteId = (int) ($dados['cliente_id'] ?? 0);
        $usuarioId = (int) ($dados['usuario_id'] ?? 0);
        $contaId = !empty($dados['conta_id']) ? (int) $dados['conta_id'] : null;
        $etapa = trim((string) ($dados['etapa'] ?? ''));
        $assunto = (string) ($dados['assunto'] ?? '');
        $descricao = trim(strip_tags((string) ($dados['descricao'] ?? '')));
        $periodo = (string) ($dados['periodo'] ?? 'qualquer');
        $horario = trim(strip_tags((string) ($dados['horario'] ?? '')));

        if($clienteId <= 0 || $usuarioId <= 0){
            throw new \InvalidArgumentException('Cliente ou usuário inválido.');
        }
        if($etapa === '' || !preg_match('/^[a-z0-9_]{1,80}$/', $etapa)){
            throw new \InvalidArgumentException('Etapa do onboarding inválida.');
        }
        if(!array_key_exists($assunto, self::ASSUNTOS)){
            throw new \InvalidArgumentException('Selecione o motivo do pedido de ajuda.');
        }
        if(!array_key_exists($periodo, self::PERIODOS)){
            throw new \InvalidArgumentException('Selecione um período válido para contato.');
        }
        if(mb_strlen($descricao) > 1000){
            throw new \InvalidArgumentException('A descrição deve possuir no máximo 1000 caracteres.');
        }
        if(mb_strlen($horario) > 120){
            throw new \InvalidArgumentException('O detalhe de horário deve possuir no máximo 120 caracteres.');
        }
        if($contaId !== null && !$this->contaPertenceAoCliente($clienteId, $contaId)){
            throw new \InvalidArgumentException('O WhatsApp informado não pertence a este cliente.');
        }

        $duplicada = $contaId
            ? $this->db->prepare("SELECT OSS_ID FROM onboarding_suporte_solicitacoes
                WHERE CLI_ID=? AND MTA_ID=? AND OSS_Status IN ('aberta','em_atendimento') LIMIT 1")
            : $this->db->prepare("SELECT OSS_ID FROM onboarding_suporte_solicitacoes
                WHERE CLI_ID=? AND MTA_ID IS NULL AND OSS_Status IN ('aberta','em_atendimento') LIMIT 1");
        $duplicada->execute($contaId ? [$clienteId, $contaId] : [$clienteId]);

        if($duplicada->fetchColumn()){
            throw new \DomainException('Já existe uma solicitação de suporte em aberto para esta configuração.');
        }

        $sql = $this->db->prepare("
            INSERT INTO onboarding_suporte_solicitacoes
                (CLI_ID, MTA_ID, USU_ID, OSS_Etapa, OSS_Assunto, OSS_Descricao,
                 OSS_PeriodoPreferido, OSS_HorarioDetalhe, OSS_Status, OSS_CriadaEm, OSS_AtualizadaEm)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'aberta', NOW(), NOW())
        ");
        $sql->execute([
            $clienteId,
            $contaId,
            $usuarioId,
            $etapa,
            $assunto,
            $descricao !== '' ? $descricao : null,
            $periodo,
            $horario !== '' ? $horario : null
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function listarAdmin($status = null)
    {
        $params = [];
        $where = '';
        if($status && in_array($status, self::STATUS, true)){
            $where = 'WHERE s.OSS_Status = ?';
            $params[] = $status;
        }

        $sql = $this->db->prepare("
            SELECT s.*, c.CLI_Nome, c.CLI_Telefone, c.CLI_Email,
                   m.MTA_Nome, m.MTA_NumeroTelefone,
                   u.USU_Nome AS SolicitanteNome,
                   a.USU_Nome AS AdminNome
            FROM onboarding_suporte_solicitacoes s
            INNER JOIN clientes c ON c.CLI_ID = s.CLI_ID
            LEFT JOIN meta_contas m
                ON m.MTA_ID = s.MTA_ID
                AND m.CLI_ID = s.CLI_ID
            LEFT JOIN usuarios u ON u.USU_ID = s.USU_ID
            LEFT JOIN usuarios a ON a.USU_ID = s.USU_Admin_ID
            {$where}
            ORDER BY
                FIELD(s.OSS_Status, 'aberta','em_atendimento','concluida','cancelada'),
                s.OSS_CriadaEm DESC,
                s.OSS_ID DESC
        ");
        $sql->execute($params);

        return $sql->fetchAll(PDO::FETCH_ASSOC);
    }

    public function atualizarStatus($id, $status, $adminId)
    {
        if((int) $id <= 0 || !in_array($status, self::STATUS, true)){
            throw new \InvalidArgumentException('Situação de atendimento inválida.');
        }

        $sql = $this->db->prepare("
            UPDATE onboarding_suporte_solicitacoes
            SET OSS_Status = ?,
                USU_Admin_ID = ?,
                OSS_AtualizadaEm = NOW(),
                OSS_EncerradaEm = CASE
                    WHEN ? IN ('concluida','cancelada') THEN COALESCE(OSS_EncerradaEm, NOW())
                    ELSE NULL
                END
            WHERE OSS_ID = ?
        ");
        $sql->execute([$status, (int) $adminId, $status, (int) $id]);

        if($sql->rowCount() < 1){
            throw new \RuntimeException('Solicitação de suporte não encontrada.');
        }

        return true;
    }
}
