<?php

namespace Models;

use Core\Database;
use PDO;

class IndicacaoCampanha
{
    private $db;

    public function __construct($db = null)
    {
        $this->db = $db ?: Database::getInstance();
    }

    public function buscar($id, $forUpdate = false)
    {
        $sql = 'SELECT * FROM indicacao_campanhas WHERE ICP_ID=?';
        if($forUpdate && $this->driver() === 'mysql'){
            $sql .= ' FOR UPDATE';
        }
        $s = $this->db->prepare($sql);
        $s->execute([(int) $id]);
        return $s->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function listar()
    {
        return $this->db->query('SELECT * FROM indicacao_campanhas ORDER BY ICP_CriadoEm DESC, ICP_ID DESC')->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buscarPublicaAtiva($forUpdate = false)
    {
        $sql = "SELECT * FROM indicacao_campanhas WHERE ICP_Ativo='S' AND ICP_Publica='S' LIMIT 1";
        if($forUpdate && $this->driver() === 'mysql'){
            $sql .= ' FOR UPDATE';
        }
        $s = $this->db->query($sql);
        return $s->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function buscarPublicaElegivel($forUpdate = false)
    {
        $sql = "SELECT * FROM indicacao_campanhas WHERE ICP_Ativo='S' AND ICP_Publica='S' AND (ICP_DataInicio IS NULL OR ICP_DataInicio <= CURRENT_TIMESTAMP) AND (ICP_DataFim IS NULL OR ICP_DataFim >= CURRENT_TIMESTAMP) LIMIT 1";
        if($forUpdate && $this->driver() === 'mysql'){
            $sql .= ' FOR UPDATE';
        }
        $s = $this->db->query($sql);
        return $s->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function criar(array $d)
    {
        $s = $this->db->prepare('INSERT INTO indicacao_campanhas (ICP_Nome,ICP_Descricao,ICP_Percentual,ICP_DataInicio,ICP_DataFim,ICP_Ativo,ICP_Publica,ICP_RegrasSnapshot,ICP_CriadoPor_USU_ID) VALUES (?,?,?,?,?,?,?,?,?)');
        $s->execute([
            $d['nome'],
            $d['descricao'] ?? null,
            $d['percentual'],
            $d['data_inicio'] ?? null,
            $d['data_fim'] ?? null,
            $d['ativo'] ?? 'N',
            $d['publica'] ?? 'N',
            isset($d['regras']) ? json_encode($d['regras'], JSON_UNESCAPED_UNICODE) : null,
            $d['usuario_id'] ?? null
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function atualizarStatus($id, $ativo, $publica = null)
    {
        $sql = 'UPDATE indicacao_campanhas SET ICP_Ativo=?'.($publica !== null ? ', ICP_Publica=?' : '').' WHERE ICP_ID=?';
        $args = $publica !== null ? [$ativo,$publica,(int) $id] : [$ativo,(int) $id];
        $s = $this->db->prepare($sql);
        return $s->execute($args);
    }

    public function atualizarConfiguracao($id, array $dados)
    {
        $s = $this->db->prepare('UPDATE indicacao_campanhas SET ICP_Nome=?, ICP_Percentual=?, ICP_DataInicio=?, ICP_DataFim=?, ICP_Publica=? WHERE ICP_ID=?');
        return $s->execute([
            $dados['nome'],
            $dados['percentual'],
            $dados['data_inicio'],
            $dados['data_fim'],
            $dados['publica'],
            (int) $id
        ]);
    }

    private function driver()
    {
        return $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
    }
}
